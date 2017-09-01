<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Dubbo\Exception\DubboCodecException;
use ZanPHP\HessianLite\Factory;
use ZanPHP\HessianLite\RuleResolver;

// FIXME dubbo/hessian-lite/src/main/java/com/alibaba/com/caucho/hessian/io/SerializerFactory.java
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

    // FIXME TEST
    public static function getJavaTypeDefaultUnSerialize(JavaType $type)
    {
        $unserialize = null;
        switch($type) {
            case JavaType::$T_void:
                $unserialize = function($v) { return ""; };
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

    // FIXME TEST
    // byte string ? int ?
    public static function getJavaTypeDefaultSerialize(JavaType $type)
    {
        $serialize = null;

        switch($type) {
            case JavaType::$T_void:
                $serialize = function($v) { return ""; };
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
}