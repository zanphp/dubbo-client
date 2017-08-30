<?php

namespace ZanPHP\Dubbo;


use ZanPHP\HessianLite\Factory;

class Hessian2Output implements Output
{
    private $writer;

    public function __construct()
    {
        $this->writer = Factory::getWriter();
    }

    public function write($v)
    {
        return $this->writer->writeValue($v);
    }

    public function writeBool($v)
    {
        return $this->writer->writeBool($v);
    }

    public function writeByte($v)
    {
        return pack("C", $v);
    }

    public function writeShort($v)
    {
        return $this->writer->writeInt($v);
    }

    public function writeInt($v)
    {
        return $this->writer->writeInt($v);
    }

    public function writeLong($v)
    {
        return $this->writer->writeInt($v);
    }

    public function writeFloat($v)
    {
        return $this->writer->writeDouble($v);
    }

    public function writeDouble($v)
    {
        return $this->writer->writeDouble($v);
    }

    public function writeString($v)
    {
        return $this->writer->writeSmallString($v);
    }

    public function writeBin($v)
    {
        return $this->writer->writeBinary($v);
    }

    public function writeObject(JavaValue $obj, JavaType $type = null)
    {
        return $obj->serialize();
    }
}