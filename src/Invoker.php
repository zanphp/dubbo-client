<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Dubbo\Exception\RpcException;

interface Invoker
{
    /**
     * get service interface.
     * return Class<T> class
     */
    public function getInterface();

    /**
     * @param Invocation invocation
     * @return Result
     * @throws RpcException
     */
    public function invoke(Invocation $invocation);
}