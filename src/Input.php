<?php

namespace ZanPHP\Dubbo;


interface Input
{
    public function read();

    public function readBool();

    public function readByte();

    public function readShort();

    public function readInt();

    public function readLong();

    public function readFloat();

    public function readDouble();

    public function readString();

    public function readBytes();

    public function readObject(JavaType $type = null);
}