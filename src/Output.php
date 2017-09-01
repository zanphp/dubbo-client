<?php

namespace ZanPHP\Dubbo;


interface Output
{
    public function write($v);

    public function writeBool($v);

    public function writeByte($v);

    public function writeShort($v);

    public function writeInt($v);

    public function writeLong($v);

    public function writeFloat($v);

    public function writeDouble($v);

    public function writeString($v);

    public function writeBin($v);

    public function writeJavaValue(JavaValue $obj);
}