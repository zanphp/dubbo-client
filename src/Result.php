<?php

namespace ZanPHP\Dubbo;


interface Result
{
    public function getValue();

    public function getException();

    /**
     * @return JavaType
     */
    public function getType();

    public function getAttachments();
}