<?php

namespace ZanPHP\Dubbo;


interface Serialization
{
    /**
     * @return int  byte
     */
    public function getContentTypeId();

    /**
     * @return string
     */
    public function getContentType();


    /**
     * @return Output
     */
    public function serialize();

    /**
     * @param $bin
     * @return Input
     */
    public function deserialize($bin);
}