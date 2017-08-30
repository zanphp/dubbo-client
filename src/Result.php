<?php

namespace ZanPHP\Dubbo;


interface Result
{
    public function getValue();

    public function getException();

    public function getAttachments();
}