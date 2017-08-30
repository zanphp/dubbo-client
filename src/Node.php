<?php

namespace ZanPHP\Dubbo;


interface Node
{
    /**
     * @return URL.
     */
    public function getUrl();

    public function isAvailable();

    public function destroy();
}