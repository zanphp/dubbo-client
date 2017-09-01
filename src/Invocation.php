<?php

namespace ZanPHP\Dubbo;


interface Invocation
{
    public function getMethodName();

    public function getParameterTypes();

    public function getArguments();

    public function getAttachments();

    public function getAttachment($key, $defaultValue);
}