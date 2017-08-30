<?php

namespace ZanPHP\Dubbo;


class Hessian2Serialization implements Serialization
{
    const ID = 2;

    public function getContentTypeId()
    {
        return static::ID;
    }

    public function getContentType()
    {
        return "x-application/hessian2";
    }

    /**
     * @return Output
     */
    public function serialize()
    {
        return new Hessian2Output();
    }

    /**
     * @return Input
     */
    public function deserialize($bin)
    {
        return new Hessian2Input($bin);
    }
}

CodecSupport::registerSerialization(new Hessian2Serialization());