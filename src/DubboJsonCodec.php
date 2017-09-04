<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Support\Json;

/**
 * Class DubboJsonCodec
 * @package ZanPHP\Dubbo
 *
 * 处理新加入的Json序列化泛化调用方式
 * String $invoke(String method, String[] parameterTypes, String jsonString) throws GenericException;
 */
class DubboJsonCodec extends DubboCodec
{
    protected function encodeRequestData(Output $out, RpcInvocation $inv)
    {
        $buf = "";
        $buf .= $out->writeString($inv->getVersion() ?: Constants::DUBBO_VERSION);
        $buf .= $out->writeString($inv->getServiceName());
        $buf .= $out->writeString($inv->getMethodVersion());
        $buf .= $out->writeString($inv->getMethodName());

        $buf .= $out->writeString(JavaType::types2desc($inv->getParameterTypes()));

        $args = [];
        foreach ($inv->getArguments() as $i => $arg) {
            // jsonString 需要map, 且按照约定 arg0, arg1, ...
            $args["arg$i"] = $arg->getValue();
        }

        if (empty($args)) {
            $args = "{}";
        } else {
            $args = Json::encode($args);
        }
        $buf .= $out->writeString($args);

        $attach = $inv->getAttachments();
        if (empty($attach)) {
            $attach = "{}";
        } else {
            $attach = Json::encode($attach ?: []);
        }
        $buf .= $out->writeString($attach);

        return $buf;
    }
}