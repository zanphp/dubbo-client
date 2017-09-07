<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Dubbo\Exception\DubboCodecException;
use ZanPHP\Dubbo\Exception\JavaException;

class RpcResult implements Result
{
    private $value;
    private $exception;
    /** @var  JavaType */
    private $type;
    private $attachments;

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function setException($exception)
    {
        $this->exception = $exception;
    }

    public function getType()
    {
        if ($this->type === null) {
            $this->type = JavaType::$T_Unknown;
        }
        return $this->type;
    }

    public function setType(JavaType $type)
    {
        $this->type = $type;
    }

    public function getAttachments()
    {
        return $this->attachments;
    }

    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;
        return $this;
    }

    public static function decode(Input $in, $ctx)
    {
        $expect = null;
        $genericProtocol = null;

        if ($ctx instanceof ClientContext) {
            $expect = $ctx->getReturnType();
            $genericProtocol = $ctx->getGenericInvokeProtocol();
        }

        $self = new static();
        $flag = $in->read(); // readInt
        switch ($flag) {
            case DubboCodec::RESPONSE_NULL_VALUE:
                $self->value = null;
                break;
            case DubboCodec::RESPONSE_VALUE:
                if ($expect instanceof JavaType) {
                    $unserialize = $expect->getUnserialize();
                    $self->value = $unserialize($in);
                } else {
                    $self->value = $in->read(); // readAll ?
                }
                $self->value = CodecSupport::generalize($genericProtocol, $self->value);
                break;
            case DubboCodec::RESPONSE_WITH_EXCEPTION:
                $ex = $in->readObject();
                if (!($ex instanceof \Throwable) && !($ex instanceof \Exception)) {
                    $ex = new JavaException($ex);
                }
                $self->exception = $ex;
                break;
            default:
                throw new DubboCodecException("Unknown result flag, expect '0' '1' '2', get $flag");
        }
        return $self;
    }


}