<?php

namespace ZanPHP\Dubbo;


use ZanPHP\HessianLite\Factory;
use ZanPHP\HessianLite\Utils;

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
//        if ($obj === null) {
//            return $this->writer->writeNull();
//        } else if (is_array($obj)) {
//            if (Utils::isListIterate($obj)) {
//                return $this->writer->writeArray($obj);
//            } else {
//                $type = '';
//                if (isset($obj['$type'])) {
//                    $type = $obj['$type'];
//                    unset($obj['$type']);
//                }
//                return $this->writer->writeMap($obj, $type);
//            }
//        } else if (is_object($obj)) {
//            return $this->writer->writeObject($obj);
//        } else {
//            if ($type) {
//                $serialization = $type->getSerialization();
//                if ($serialization) {
//                    return $serialization($obj);
//                }
//            }
//            throw new DubboCodecException("UnExpected var to writeObject by hessian2");
//        }
    }
}