<?php

use \ZanPHP\Contracts\ConnectionPool\Connection;
use \ZanPHP\Container\Container;
use \ZanPHP\Dubbo\Client;


$container = Container::getInstance();
$container->bind("heartbeatable:dubbo", function($_, $args) {
    /** @var Connection  $novaConnection */
//    $novaConnection = $args[0];
//    $hbServName = "com.youzan.service.test";
//    return NovaClient::getInstance($novaConnection, $hbServName);
});


return [
    \ZanPHP\NovaCodec\NovaCodec::class => [
        "interface" => \ZanPHP\Contracts\Codec\Codec::class,
        "id" => "codec:dubbo",
        "shared" => true,
    ],
];