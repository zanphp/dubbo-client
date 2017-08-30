<?php

namespace ZanPHP\Dubbo;


class RpcInvocation implements Invocation
{
    private $methodName;
    /**
     * @var JavaType[]
     */
    private $parameterTypes = [];
    /**
     * @var JavaValue[]
     */
    private $arguments = [];
    private $attachments = [];

    /**
     * FIXME
     * @var
     */
    private $invoker;

    public function getMethodName()
    {
        return $this->methodName;
    }

    public function setMethodName($methodName)
    {
        $this->methodName = $methodName;
    }

    public function getParameterTypes()
    {
        return $this->parameterTypes;
    }

    public function setParameterTypes($parameterTypes)
    {
        $this->parameterTypes = $parameterTypes;
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param JavaValue[] $arguments
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
    }

    public function getAttachments()
    {
        return new JavaValue(JavaType::$T_Map, $this->attachments);
    }

    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;
    }

    public function setAttachment($key, $value)
    {
        $this->attachments[$key] = $value;
    }

    public function removeAttachment($key)
    {
        unset($this->attachments[$key]);
    }

    public function getAttachment($key, $default = null)
    {
        if (isset($this->attachments[$key])) {
            return $this->attachments[$key];
        }
        return $default;
    }

    public static function decode(Input $in)
    {
        $self = new static();

        $self->attachments[Constants::DUBBO_VERSION_KEY] =  $in->readString();
        $self->attachments[Constants::PATH_KEY] =  $in->readString();
        $self->attachments[Constants::VERSION_KEY] =  $in->readString();

        $self->methodName = $in->readString();

        $desc = $in->readString();
        $args = [];
        $self->parameterTypes = JavaType::getByDescs($desc);
        $argLen = count($self->parameterTypes);
        // read args
        for ($i = 0; $i < $argLen; $i++) {
            // FIXME 做一个类型映射表, 按照java的类型读数据....
            $type = $self->parameterTypes[$i];
            $args[] = $in->read();
        }

        // read attachments
        // !!! 使用object来读取map
        $map = $in->readObject();
        if (is_array($map) && $map) {
            $self->attachments = $map;
        }

        //decode argument
        foreach ($args as $i => $arg) {
            $args[$i] = static::decodeInvocationArgument($self, $self->parameterTypes[$i], $arg);
        }
        $self->arguments = $args;

        return $self;
    }

    public static function decodeInvocationArgument(RpcInvocation $inv, JavaType $paraType, $rawArg)
    {
        // TODO
        //如果是callback，则创建proxy到客户端，方法的执行可通过channel调用到client端的callback接口
        //decode时需要根据channel及env获取url
        // /dubbo-rpc/dubbo-rpc-default/src/main/java/com/alibaba/dubbo/rpc/protocol/dubbo/CallbackServiceCodec.java

        return new JavaValue($paraType, $rawArg);
    }

    public static function encodeInvocationArgument(RpcInvocation $inv, $paraType, $arg)
    {
        // FIXME
        return $arg;
    }

    /**
     * @return Invoker
     */
    public function getInvoker()
    {
        // FIXME
        return null;
    }
}