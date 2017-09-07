<?php

namespace ZanPHP\Dubbo;


class RpcInvocation implements Invocation
{
    private $version = Constants::DUBBO_VERSION;

    private $serviceName;

    private $methodName;

    private $methodVersion = "0.0.0";

    /**
     * @var JavaType[]
     */
    private $parameterTypes = [];
    /**
     * @var JavaValue[]
     */
    private $arguments = [];
    private $attachments = [];

    private $isJsonSerialize = false;

    public function isJsonSerialize()
    {
        return $this->isJsonSerialize;
    }

    public function setJsonSerialize($isJsonSerialize)
    {
        $this->isJsonSerialize = $isJsonSerialize;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function getServiceName()
    {
        return $this->serviceName;
    }

    public function setServiceName($serviceName)
    {
        $this->serviceName = $serviceName;
    }

    public function getMethodName()
    {
        return $this->methodName;
    }

    public function setMethodName($methodName)
    {
        $this->methodName = $methodName;
    }

    public function getMethodVersion()
    {
        return $this->methodVersion;
    }

    public function setMethodVersion($methodVersion)
    {
        $this->methodVersion = $methodVersion;
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
        return $this->attachments;
    }

    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;
    }

    public function addAttachment($key, $value)
    {
        $this->attachments[$key] = $value;
    }

    public function addAttachments(array $attachments)
    {
        $this->attachments = array_merge($this->attachments, $attachments);
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

        $self->version = $in->readString();
        $self->serviceName = $in->readString();
        $self->methodVersion = $in->readString();
        $self->methodName = $in->readString();

        $desc = $in->readString();
        $args = [];
        $self->parameterTypes = JavaType::descs2type($desc);
        $argLen = count($self->parameterTypes);

        // read args
        for ($i = 0; $i < $argLen; $i++) {
            $type = $self->parameterTypes[$i];
            $unserialize = $type->getUnserialize();
            $args[] = $unserialize($in);
        }

        // read attachments
        // FIXME test
        $map = $in->read(); // readObject??
        if (is_array($map) && $map) {
            $self->attachments = $map;
        }

        //decode argument
        foreach ($args as $i => $arg) {
            $args[$i] = new JavaValue($self->parameterTypes[$i], $arg);
        }
        $self->arguments = $args;

        return $self;
    }
}