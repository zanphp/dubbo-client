<?php

namespace ZanPHP\Dubbo;

require  __DIR__ . "/../vendor/autoload.php";
require  __DIR__ . "/../../hessian-lite/vendor/autoload.php";


//$codec = new DubboCodec();
//$req = new Request();
//$req->setEvent(Request::HEARTBEAT_EVENT);
//$ping = $codec->encode($req);


//$t = JavaType::createArray(JavaType::createArray(JavaType::$T_Integer));
//echo $t->getDesc();
//$personType = JavaType::createClass("com.youzan.Person");
//$personArray = JavaType::createArray($personType);
//echo $personArray->getName();
//echo $personArray->getDesc();


$req = new Request();
$invoke = new RpcInvocation();
$invoke->addAttachment(Constants::DUBBO_VERSION_KEY, DubboCodec::DUBBO_VERSION);
$invoke->addAttachment(Constants::PATH_KEY, "com.alibaba.dubbo.demo.DemoService");
$invoke->addAttachment(Constants::VERSION_KEY, "0.0.0");
$invoke->setMethodName("sayHello");
$invoke->setParameterTypes([JavaType::$T_String]);
$invoke->setArguments([new JavaValue(JavaType::$T_String, "world")]);

$req->setVersion(DubboCodec::DUBBO_VERSION);
$req->setTwoWay(true);
$req->setData($invoke);

$codec = new DubboCodec();
echo bin2hex($codec->encode($req));


$ip = "127.0.0.1";
$port = 20880;

$client = new Client();
$client->connect($ip, $port);
