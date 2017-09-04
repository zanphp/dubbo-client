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
        $buf .= $this->encodeArguments($out, $inv);
        $buf .= $this->encodeAttachments($out, $inv);

        return $buf;
    }

    private function encodeArguments(Output $out, RpcInvocation $inv)
    {
        $args = [];
        foreach ($inv->getArguments() as $i => $arg) {
            // jsonString 需要map, 且按照约定 arg0, arg1, ...
            $args["arg$i"] = $arg->getValue();
        }

        if (empty($args)) {
            $args = "{}";
        } else {
            $this->convertEnum($args);
            $args = Json::encode($args);
        }

        return $out->writeString($args);
    }

    private function encodeAttachments(Output $out, RpcInvocation $inv)
    {
        $attach = $inv->getAttachments();
        if (empty($attach)) {
            $attach = "{}";
        } else {
            $attach = Json::encode($attach ?: []);
        }
        return $out->writeString($attach);
    }

    // hessian 序列化 java enum 序列化成 对象
    // json 序列化 java enum 序列化成 string
    private function convertEnum(&$args)
    {
        if (is_array($args) && $args) {
            if (isset($args["__enum"])) {
                $args = strval($args["name"]);
            } else {
                foreach ($args as &$arg) {
                    $this->convertEnum($arg);
                }
            }
        } else if (is_object($args)) {
            $clazz = new \ReflectionClass($args);
            if ($clazz->hasProperty("__enum")) {
                $prop = $clazz->getProperty("name");
                $prop->setAccessible(true);
                $args = $prop->getValue($args);
            } else {
                foreach ($args as $key => &$val) {
                    $this->convertEnum($val);
                }
            }
        }
    }
}