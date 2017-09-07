<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Dubbo\Exception\DubboCodecException;
use ZanPHP\HessianLite\Factory;
use ZanPHP\HessianLite\RuleResolver;
use ZanPHP\Support\Json;


class CodecSupport
{
    /**
     * @var Serialization[]
     */
    private static $ID_SERIALIZATION_MAP = [];

    public static function getSerializationById($id)
    {
        if (isset(self::$ID_SERIALIZATION_MAP[$id])) {
            return self::$ID_SERIALIZATION_MAP[$id];
        }
        throw new DubboCodecException("Serialization id $id not support");
    }

    public static function registerSerialization(Serialization $serialization)
    {
        self::$ID_SERIALIZATION_MAP[$serialization->getContentTypeId()] = $serialization;
    }

    public static function generalize($protocol, $value)
    {
        if ($protocol === "hessian2") {
            return self::generalizeHessian2Response($value);
        } else if ($protocol === "json") {
            return self::generalizeJsonResponse($value);
        } else {
            return $value;
        }
    }

    private static function generalizeHessian2Response($value)
    {
        if (is_array($value)) {
            if (isset($value["class"])) {
                $phpClass = str_replace([".", '$'], ["\\", "__"], $value["class"]);
                if (class_exists($phpClass)) {
                    $obj = new $phpClass;
                    foreach ($value as $k => $v) {
                        $obj->$k = self::generalizeHessian2Response($v);
                    }
                    unset($obj->class);
                    return $obj;
                } else {
                    return $value;
                }
            } else {
                $newValue = [];
                foreach ($value as $k => $v) {
                    $newValue[$k] = self::generalizeHessian2Response($v);
                }
                return $newValue;
            }
        } else {
            return $value;
        }
    }

    private static function generalizeJsonResponse($value)
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

    public static function encodeJsonGenericArgs(array $args)
    {
        $r = [];
        foreach (array_values($args) as $i => $arg) {
            // jsonString 需要map, 且按照约定 arg0, arg1, ...
            $r["arg$i"] = $arg;
        }

        if (empty($r)) {
            return "{}";
        } else {
            self::convertEnumHelper($r);
            return Json::encode($r);
        }
    }

    // hessian 序列化 java enum 序列化成 对象
    // json 序列化 java enum 序列化成 string
    private static function convertEnumHelper(&$args)
    {
        if (is_array($args) && $args) {
            if (isset($args["__enum"])) {
                $args = strval($args["name"]);
            } else {
                foreach ($args as &$arg) {
                    self::convertEnumHelper($arg);
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
                    self::convertEnumHelper($val);
                }
            }
        }
    }

    public static function encodeJsonAttachments(Output $out, RpcInvocation $inv)
    {
        $attach = $inv->getAttachments();
        if (empty($attach)) {
            $attach = "{}";
        } else {

            $attach = Json::encode($attach ?: []);
        }
        return $out->writeString($attach);
    }

    // FIXME TEST
    // byte string ? int ?
    public static function getJavaTypeDefaultSerialize(JavaType $type)
    {
        $serialize = null;

        switch($type) {
            case JavaType::$T_Unknown:
                $serialize = function($v) { return Factory::getWriter()->writeValue($v); };
                break;

            case JavaType::$T_Void:
            case JavaType::$T_void:
                $serialize = function($v) { return null; };
                break;

            case JavaType::$T_Boolean:
            case JavaType::$T_boolean:
                $serialize = function($v) { return Factory::getWriter()->writeBool($v); };
                break;

            case JavaType::$T_byte:
            case JavaType::$T_Byte:
            case JavaType::$T_Integer:
            case JavaType::$T_int:
            case JavaType::$T_short:
            case JavaType::$T_Short:
                $serialize = function($v) { return Factory::getWriter()->writeInt($v); };
                break;

            // FIXME writeLong
            case JavaType::$T_long:
            case JavaType::$T_Long:
                $serialize = function($v) { return Factory::getWriter()->writeInt($v); };
                break;

            case JavaType::$T_char:
            case JavaType::$T_Character:
            case JavaType::$T_String:
                $serialize = function($v) { return Factory::getWriter()->writeString($v); };
                break;

            case JavaType::$T_double:
            case JavaType::$T_Double:
            case JavaType::$T_float:
            case JavaType::$T_Float:
                $serialize = function($v) { return Factory::getWriter()->writeDouble($v); };
                break;

            case JavaType::$T_HashMap:
            case JavaType::$T_Map:
            case JavaType::$T_Dictionary:
                $serialize = function($v) { return Factory::getWriter()->writeMap($v); };
                break;

            case JavaType::$T_Strings:
            case JavaType::$T_List:
            case JavaType::$T_Set:
                $serialize = function($v) { return Factory::getWriter()->writeArray($v); };
                break;


            case JavaType::$T_chars:
                $serialize = function($v) { return Factory::getWriter()->writeBinary($v); };
                break;

            case JavaType::$T_Object:
                $serialize = function($v) { return Factory::getWriter()->writeObject($v); };
                break;

            case JavaType::$T_Iterator:
            case JavaType::$T_Enumeration:
            default:
                if ($type->isArray()) {
                    $serialize = function($v) { return Factory::getWriter()->writeArray($v); };
                } else if (!$type->isPrimitive()) {
                    $serialize = function($v) { return Factory::getWriter()->writeObject($v); };
                } else {
                    $serialize = function($v) { return Factory::getWriter()->writeValue($v); };
                }
        }

        return $serialize;
    }

    // FIXME TEST
    public static function getJavaTypeDefaultUnSerialize(JavaType $type)
    {
        $unserialize = null;
        switch($type) {
            case JavaType::$T_Unknown:
                $unserialize = function(Input $in) { return $in->read(); };
                break;

            case JavaType::$T_void:
            case JavaType::$T_Void:
                $unserialize = function($v) { return null; };
                break;

            case JavaType::$T_Boolean:
            case JavaType::$T_boolean:
                $unserialize = function(Input $in) { return $in->readBool(); };
                break;

            case JavaType::$T_byte:
            case JavaType::$T_Byte:
                $unserialize = function(Input $in) { return $in->readByte(); };
                break;

            case JavaType::$T_short:
            case JavaType::$T_Short:
            case JavaType::$T_Integer:
            case JavaType::$T_int:
                $unserialize = function(Input $in) { return $in->readInt(); };
                break;

            case JavaType::$T_long:
            case JavaType::$T_Long:
                $unserialize = function(Input $in) { return $in->readLong(); };
                break;

            case JavaType::$T_float:
            case JavaType::$T_Float:
            case JavaType::$T_double:
            case JavaType::$T_Double:
                $unserialize = function(Input $in) { return $in->readDouble(); };
                break;

            case JavaType::$T_char:
            case JavaType::$T_Character:
            case JavaType::$T_String:
                $unserialize = function(Input $in) { return $in->readString(); };
                break;

            case JavaType::$T_chars:
                $unserialize = function(Input $in) { return $in->read(RuleResolver::T_BINARY); };
                break;

            case JavaType::$T_Strings:
            case JavaType::$T_List:
            case JavaType::$T_Set:
                $unserialize = function(Input $in) { return $in->read(RuleResolver::T_LST); };
                break;

            case JavaType::$T_HashMap:
            case JavaType::$T_Map:
            case JavaType::$T_Dictionary:
                $unserialize = function(Input $in) { return $in->read(RuleResolver::T_MAP); };
                break;

            case JavaType::$T_Iterator:
            case JavaType::$T_Enumeration:
            case JavaType::$T_Object:
            default:
                if ($type->isArray()) {
                    $unserialize = function(Input $in) { return $in->read(RuleResolver::T_LST); };
                } else if (!$type->isPrimitive()) {
                    $unserialize = function(Input $in) { return $in->readObject(); };
                } else {
                    $unserialize = function(Input $in) { return $in->read(); }; // readAll() ??
                }
        }
        return $unserialize;
    }
}