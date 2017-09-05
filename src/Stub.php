<?php

namespace ZanPHP\Dubbo;


use ZanPHP\NovaConnectionPool\NovaClientConnectionManager as DubboClientConnectionManager;
use ZanPHP\Contracts\ConnectionPool\Connection;
use ZanPHP\NovaConnectionPool\Exception\NoFreeConnectionException;


class Stub
{
    protected static $__service = "";
    protected static $__methods = [];

    final public function __genericCall($method, array $args)
    {
        $connection = (yield DubboClientConnectionManager::getInstance()->get("dubbo", "com.youzan.service", static::$__service, $method));
        if (!($connection instanceof Connection)) {
            throw new NoFreeConnectionException("get dubbo connection error");
        }

        $signature = static::$__methods[$method];
        if (!$signature instanceof JavaMethodSignature) {
            static::$__methods[$method] = new JavaMethodSignature($signature);
        }

        /** @var JavaMethodSignature $methodSignature */
        $methodSignature = static::$__methods[$method];
        $method = $methodSignature->getMethodName(); // 方法重载
        $timeout = (yield getContext("dubbo::timeout", DubboClient::DEFAULT_SEND_TIMEOUT));

        $dubboClient = new DubboClient($connection, static::$__service);
        yield $dubboClient->genericCall($method, $args, $methodSignature, $timeout);
    }
}