<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Support\Json;

class DubboJsonCodec extends DubboCodec
{
    protected function decodeRequest(Input $in, $id, $flag)
    {
        // FIXME
    }

    protected function encodeRequestData(Output $out, RpcInvocation $inv)
    {
        $buf = "";
        $buf .= $out->writeString($inv->getVersion() ?: Constants::DUBBO_VERSION);
        $buf .= $out->writeString($inv->getServiceName());
        $buf .= $out->writeString($inv->getMethodVersion());
        $buf .= $out->writeString($inv->getMethodName());

//        FIXME ?!
//        if (substr(self::DUBBO_VERSION, 0, 3) === "2.8") {
//            $buf .= $out->write(-1);
//        }

        $buf .= $out->writeString(JavaType::types2desc($inv->getParameterTypes()));

        $args = [];
        foreach ($inv->getArguments() as $i => $arg) {
            $args[] = $arg->getValue();
        }
        $buf .= Json::encode($args);

        $buf .= Json::encode($inv->getAttachments() ?: []);
        return $buf;
    }
}