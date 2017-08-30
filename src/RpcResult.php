<?php

namespace ZanPHP\Dubbo;


class RpcResult implements Result
{
    private $value;
    private $exception;
    private $attachments;

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function setException($exception)
    {
        $this->exception = $exception;
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

    public static function decode(Input $in)
    {
        $self = new static();
        $flag = $in->readByte();
        switch ($flag) {
            case DubboCodec::RESPONSE_NULL_VALUE:
                break;
            case DubboCodec::RESPONSE_VALUE:
                $self->setValue($in->readObject());
                break;
            case DubboCodec::RESPONSE_WITH_EXCEPTION:
                $self->setException($in->readObject());
                break;
            default:
                throw new DubboCodecException("Unknown result flag, expect '0' '1' '2', get $flag");
        }
        return $self;
    }
}