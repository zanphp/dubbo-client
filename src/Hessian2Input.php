<?php

namespace ZanPHP\Dubbo;


use ZanPHP\HessianLite\Factory;

class Hessian2Input implements Input
{
    private $parser;

    public function __construct($bin)
    {
        $this->parser = Factory::getParser($bin);
    }

    public function read()
    {
        return $this->parser->parseCheck();
    }

    public function readBool()
    {
        return $this->parser->parseCheck(null, "bool");
    }

    public function readByte()
    {
//        return unpack('C', $this->parser->read(1))[1];
        return $this->parser->readNum(1);
    }

    public function readShort()
    {
        return $this->parser->parseCheck(null, "integer");
    }

    public function readInt()
    {
        return $this->parser->parseCheck(null, "integer");
    }

    public function readLong()
    {
        return $this->parser->parseCheck(null, "long");
    }

    public function readFloat()
    {
        return $this->parser->parseCheck(null, "double");
    }

    public function readDouble()
    {
        return $this->parser->parseCheck(null, "double");
    }

    public function readString()
    {
        return $this->parser->parseCheck(null, "string");
    }

    public function readBytes()
    {
        return $this->parser->parseCheck(null, "binary");
    }

    public function readObject(JavaType $type = null)
    {
        return $this->parser->parseCheck(null, "object,list,map,null");
    }
}