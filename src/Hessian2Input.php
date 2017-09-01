<?php

namespace ZanPHP\Dubbo;


use ZanPHP\HessianLite\Factory;
use ZanPHP\HessianLite\RuleResolver;
use ZanPHP\HessianLite\StreamEOF;

class Hessian2Input implements Input
{
    private $parser;

    public function __construct($bin)
    {
        $this->parser = Factory::getParser($bin);
    }

    public function read($expect = null)
    {
        return $this->parser->parseCheck(null, $expect);
    }

    public function readBool()
    {
        return $this->parser->parseCheck(null, "bool");
    }

    public function readByte()
    {
        // return unpack('C', $this->parser->read(1))[1];
        return $this->parser->readNum(1);
    }

    public function readInt()
    {
        return $this->parser->parseCheck(null, RuleResolver::T_INTEGER);
    }

    public function readLong()
    {
        return $this->parser->parseCheck(null, RuleResolver::T_LONG);
    }

    public function readDouble()
    {
        return $this->parser->parseCheck(null, RuleResolver::T_DOUBLE);
    }

    public function readString()
    {
        return $this->parser->parseCheck(null, RuleResolver::T_STRING);
    }

    public function readBytes()
    {
        return $this->parser->parseCheck(null, RuleResolver::T_BINARY);
    }

    public function readObject(JavaType $type = null)
    {
        // FIXME
        return $this->parser->parseCheck(null, "object,list,map,null");
    }

    public function readAll()
    {
        $r = [];
        while (true) {
            try {
                $r[] = $this->parser->parseCheck();
            } catch (StreamEOF $_) {
                break;
            }
        }
        return $r;
    }
}