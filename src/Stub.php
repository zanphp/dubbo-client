<?php

namespace ZanPHP\Dubbo;


use ZanPHP\NovaConnectionPool\NovaClientConnectionManager as DubboClientConnectionManager;
use ZanPHP\Contracts\ConnectionPool\Connection;
use ZanPHP\NovaConnectionPool\Exception\NoFreeConnectionException;


class Stub
{
    protected static $__type = "";
    protected static $__methods = [];

    final public function __genericCall($method, ...$args)
    {
        static $codec;
        if (!$codec) {
            $codec = new DubboCodec();
        }

        $connection = (yield DubboClientConnectionManager::getInstance()->get("dubbo", "com.youzan.service", static::$__type, $method));
        if (!($connection instanceof Connection)) {
            throw new NoFreeConnectionException("get dubbo connection error");
        }

        $dubboClient = new DubboClient($connection, static::$__type, $codec);
        yield $dubboClient->genericCall($method, $args);
    }

    final public function __genericCallEx($method, ...$args)
    {
        static $codec;
        if (!$codec) {
            $codec = new DubboCodec();
        }

        $method = static::$__methods[$method]["name"];
        $signature = static::$__methods[$method]["signature"];

        foreach ($args as $i => $arg) {
            $args[$i] = new JavaValue($signature[$i], $arg);
        }

        $connection = (yield DubboClientConnectionManager::getInstance()->get("dubbo", "com.youzan.service", static::$__type, $method));
        if (!($connection instanceof Connection)) {
            throw new NoFreeConnectionException("get dubbo connection error");
        }

        $dubboClient = new DubboClient($connection, static::$__type, $codec);
        yield $dubboClient->genericCallEx($method, $args);
    }
}