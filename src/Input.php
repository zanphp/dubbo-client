<?php

namespace ZanPHP\Dubbo;


interface Input
{
    public function read($expect = null);

    public function readBool();

    public function readByte();

    public function readInt();

    public function readLong();

    public function readDouble();

    public function readString();

    public function readBytes();

    public function readObject(JavaType $type = null);

    public function readAll();
}