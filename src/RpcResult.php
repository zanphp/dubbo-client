<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Dubbo\Exception\DubboCodecException;
use ZanPHP\Dubbo\Exception\RpcException;

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

    // FIXME 读取并 SET Attachments
    public static function decode(Input $in)
    {
        $self = new static();
        $flag = $in->read(); // !!!!!
        switch ($flag) {
            case DubboCodec::RESPONSE_NULL_VALUE:
                break;
            case DubboCodec::RESPONSE_VALUE:
                $self->setValue($in->readObject());
                break;
            case DubboCodec::RESPONSE_WITH_EXCEPTION:
                $ex = $in->readObject();
                if (!($ex instanceof \Throwable) && !($ex instanceof \Exception)) {
                    $ex = new RpcException(print_r($ex, true));
                }
                $self->setException($ex);
                break;
            default:
                throw new DubboCodecException("Unknown result flag, expect '0' '1' '2', get $flag");
        }
        return $self;
    }
}