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

        $args = $inv->getArguments();
        foreach ($args as $arg) {
            $buf .= $out->writeJavaValue($arg);
        }

        $buf .= $out->writeJavaValue(new JavaValue(JavaType::$T_Map, $inv->getAttachments() ?: []));
        // 这里attach 暂时使用hessian序列化
        // $buf .= $this->encodeAttachments($out, $inv);

        return $buf;
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

    public static function encodeArgs(array $args)
    {
        $r = [];
        foreach (array_values($args) as $i => $arg) {
            // jsonString 需要map, 且按照约定 arg0, arg1, ...
            $r["arg$i"] = $arg;
        }

        if (empty($r)) {
            return "{}";
        } else {
            self::convertEnum($args);
            return Json::encode($args);
        }
    }

    // hessian 序列化 java enum 序列化成 对象
    // json 序列化 java enum 序列化成 string
    private static function convertEnum(&$args)
    {
        if (is_array($args) && $args) {
            if (isset($args["__enum"])) {
                $args = strval($args["name"]);
            } else {
                foreach ($args as &$arg) {
                    self::convertEnum($arg);
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
                    self::convertEnum($val);
                }
            }
        }
    }

    protected function generalize($value)
    {
        if (!self::isJSON($value)) {
            return $value;
        }

        $value = Json::decode($value);
        // FIXME 根据返回值类型把json还原成对象
        return $value;
    }

    private static function isJSON($string)
    {
        if (!is_string($string)) {
            return false;
        }

        $pcreRegex = '
  /
  (?(DEFINE)
     (?<number>   -? (?= [1-9]|0(?!\d) ) \d+ (\.\d+)? ([eE] [+-]? \d+)? )    
     (?<boolean>   true | false | null )
     (?<string>    " ([^"\\\\]* | \\\\ ["\\\\bfnrt\/] | \\\\ u [0-9a-f]{4} )* " )
     (?<array>     \[  (?:  (?&json)  (?: , (?&json)  )*  )?  \s* \] )
     (?<pair>      \s* (?&string) \s* : (?&json)  )
     (?<object>    \{  (?:  (?&pair)  (?: , (?&pair)  )*  )?  \s* \} )
     (?<json>   \s* (?: (?&number) | (?&boolean) | (?&string) | (?&array) | (?&object) ) \s* )
  )
  \A (?&json) \Z
  /six   
';
        return boolval(preg_match($pcreRegex, $string));
    }
}