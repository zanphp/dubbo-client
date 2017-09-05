<?php

namespace ZanPHP\Dubbo;


require __DIR__ . "/JavaMethodSignature.php";
require __DIR__ . "/JavaGenericType.php";
require __DIR__ . "/JavaType_Test.php";


$r = JavaMethodSignature::parseType("Map<String,Map<String,Map<A,B>>>");
print_r($r);


$signature = "xxxMethod(String,int,List<Map<String,Person>>)Map<String,Map<String,Map<A,B>>>";
$method = new JavaMethodSignature($signature);
print_r($method);


$signature  = "create(com.youzan.trade.core.service.main.create.dto.req.TradeRequestDTO)com.youzan.api.common.response.PlainResult<com.youzan.trade.core.service.main.create.dto.res.TradeCreateResponseDTO>";
$method = new JavaMethodSignature($signature);
print_r($method);



