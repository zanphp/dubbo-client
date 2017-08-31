<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Dubbo\Exception\DubboCodecException;
use ZanPHP\HessianLite\Factory;
use ZanPHP\HessianLite\Utils;

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

    // FIXME 这里实现有问题
    // map list ... 如何序列化，包装类型如何序列化
    public static function getJavaTypeDefaultSerialization(JavaType $type)
    {
        $serialization = null;
        switch($type) {
            case JavaType::$T_void:
                $serialization = function($v) {
                    return "";
                };
                break;
            case JavaType::$T_Boolean:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeBool($v);
                };
                break;
            case JavaType::$T_boolean:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeBool($v);
                };
                break;
            case JavaType::$T_Integer:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeInt($v);
                };
                break;
            case JavaType::$T_int:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeInt($v);
                };
                break;
            case JavaType::$T_short:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeInt($v);
                };
                break;
            case JavaType::$T_Short:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeInt($v);
                };
                break;
            case JavaType::$T_byte:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeString($v);
                };
                break;
            case JavaType::$T_Byte:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeString($v);
                };
                break;
            case JavaType::$T_long:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeInt($v);
                };
                break;
            case JavaType::$T_Long:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeInt($v);
                };
                break;
            case JavaType::$T_double:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeDouble($v);
                };
                break;
            case JavaType::$T_Double:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeDouble($v);
                };
                break;
            case JavaType::$T_float:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeDouble($v);
                };
                break;
            case JavaType::$T_Float:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeDouble($v);
                };
                break;
            case JavaType::$T_String:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeString($v);
                };
                break;
            case JavaType::$T_char:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeString($v);
                };
                break;
            case JavaType::$T_chars:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeArray($v);
                };
                break;
            case JavaType::$T_Character:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeString($v);
                };
                break;
            case JavaType::$T_List:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeArray($v);
                };
                break;
            case JavaType::$T_Set:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeArray($v);
                };
                break;
            case JavaType::$T_Iterator:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeObject($v);
                };
                break;
            case JavaType::$T_Enumeration:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeObject($v);
                };
                break;
            case JavaType::$T_HashMap:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeMap($v);
                };
                break;
            case JavaType::$T_Map:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeMap($v);
                };
                break;
            case JavaType::$T_Dictionary:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    return $writer->writeMap($v);
                };
                break;
            default:
                $serialization = function($v) {
                    $writer = Factory::getWriter();
                    if ($v === null) {
                        return $writer->writeNull();
                    } else if (is_object($v)) {
                        return $writer->writeObject($v);
                    } else if (is_array($v)) {
                        if (Utils::isListKeys($v)) {
                            return $writer->writeArray($v);
                        } else {
                            return $writer->writeMap($v);
                        }
                    } else {
                        // FIXME
                        throw new \Exception();
                    }
                };
        }
        return $serialization;
    }
}