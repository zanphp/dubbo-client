<?php

namespace ZanPHP\Dubbo;

use ZanPHP\Exception\System\ClassNotFoundException;

class JavaType
{
    const JVM_VOID = 'V';
    const JVM_BOOLEAN = 'Z';
    const JVM_BYTE = 'B';
    const JVM_CHAR = 'C';
    const JVM_DOUBLE = 'D';
    const JVM_FLOAT = 'F';
    const JVM_INT = 'I';
    const JVM_LONG = 'J';
    const JVM_SHORT = 'S';
    const JVM_OBJECT = "L";
    const JVM_ARRAY = '[';

    const JAVA_IDENT_REGEX = '(?:[_$a-zA-Z][_$a-zA-Z0-9]*)';
    const CLASS_DESC = '(?:L' . self::JAVA_IDENT_REGEX . '(?:\\/' . self::JAVA_IDENT_REGEX . ')*;)';
    const ARRAY_DESC = '(?:\\[+(?:(?:[VZBCDFIJS])|' . self::CLASS_DESC . '))';
    const DESC_REGEX = '(?:(?:[VZBCDFIJS])|' . self::CLASS_DESC . '|' . self::ARRAY_DESC . ')';

    private $name;
    private $isPrimitive = false;
    private $isArray = false;
    /**
     * @var JavaType
     */
    private $componentClass;
    private $desc;

    /**
     * @var callable
     */
    private $valid;

    /**
     * @var callable
     */
    private $serialization;

    public function isArray()
    {
        return $this->isArray;
    }

    public function getComponentType()
    {
        return $this->componentClass;
    }

    public function isPrimitive()
    {
        return $this->isPrimitive;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDesc()
    {
        return $this->desc;
    }

    public function getSerialization()
    {
        return $this->serialization;
    }

    public function valid($value)
    {
        $valid = $this->valid;
        if ($valid) {
            return $valid($value);
        }
        return $value;
    }

    public function setValid(callable $valid)
    {
        $this->valid = $valid;
    }

    public function setSerialization(callable $serialization)
    {
        $this->serialization = $serialization;
    }

    // -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

    private static $typeMap = [];

    public static function register(JavaType $type)
    {
        static::$typeMap[$type->getName()] = $type;
    }

    public static function getByName($name)
    {
        if (!isset(static::$typeMap[$name])) {
            return null;
        }
        return static::$typeMap[$name];
    }

    public static function createPrimitive($name, $desc)
    {
        $type = self::getByName($name);
        if ($type) {
            return $type;
        }

        $self = new static;
        $self->name = $name;
        $self->desc = $desc;
        $self->isPrimitive = true;
        $self->isArray = false;
        static::register($self);
        return $self;
    }

    public static function createClass($class)
    {
        $type = self::getByName($class);
        if ($type) {
            return $type;
        }

        $self = new static;
        $self->name = $class;
        $self->desc = self::JVM_OBJECT . str_replace('.', '/', $class);
        $self->isPrimitive = false;
        $self->isArray = false;
        static::register($self);
        return $self;
    }

    public static function createArray(JavaType $componentClass)
    {
        $type = self::getByName($componentClass->getName() . "[]");
        if ($type) {
            return $type;
        }

        $self = new static;
        $self->name = "$componentClass->name[]";
        $self->desc = self::JVM_ARRAY . $componentClass->getDesc();
        $self->isPrimitive = false;
        $self->isArray = true;
        static::register($self);
        return $self;
    }

    // -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

    /**
     * @param string $desc
     * @return JavaType
     * @throws \Exception
     */
    public static function getByDesc($desc)
    {
        if (!strlen($desc)) {
            throw new \InvalidArgumentException();
        }

        switch ($desc[0]) {
            case self::JVM_VOID:
                return JavaType::$T_void;
            case self::JVM_BOOLEAN:
                return JavaType::$T_boolean;
            case self::JVM_BYTE:
                return JavaType::$T_byte;
            case self::JVM_CHAR:
                return JavaType::$T_char;
            case self::JVM_DOUBLE:
                return JavaType::$T_double;
            case self::JVM_FLOAT:
                return JavaType::$T_float;
            case self::JVM_INT:
                return JavaType::$T_int;
            case self::JVM_LONG:
                return JavaType::$T_long;
            case self::JVM_SHORT:
                return JavaType::$T_short;
            case self::JVM_OBJECT:
                $className = str_replace('/', '.', substr($desc, 1));
                $className = rtrim($className, ";");
                $type = static::getByName($className);
                if ($type === null) {
                    $type = static::createClass($className);
                }
                return $type;
            case self::JVM_ARRAY:
                $desc = str_replace('/', '.', substr($desc, 1));
                return static::createArray(static::getByDesc($desc));
            default:
                throw new ClassNotFoundException("Class not found: $desc");
        }
    }

    /**
     * @param string $desc
     * @return JavaType[]
     */
    public static function getByDescs($desc)
    {
        if (strlen($desc) === 0) {
            return [];
        }

        $types = [];
        if (preg_match_all('#' . self::DESC_REGEX . '#', $desc, $matches)) {
            foreach ($matches[0] as $desc) {
                $types[] = static::getByDesc($desc);
            }
        }
        return $types;
    }

    // -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

    /**
     * @param JavaType[] $types
     * @return string
     */
    public static function getDescs(array $types)
    {
        if (empty($types)) {
            return "";
        }

        $descs = [];
        foreach ($types as $type) {
            $descs[] = $type->getDesc();
        }
        return implode(";", $descs) . ";";
    }

    // -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

    public static $T_void;
    public static $T_Boolean;
    public static $T_boolean;
    public static $T_Integer;
    public static $T_int;
    public static $T_short;
    public static $T_Short;
    public static $T_byte;
    public static $T_Byte;
    public static $T_long;
    public static $T_Long;
    public static $T_double;
    public static $T_Double;
    public static $T_float;
    public static $T_Float;
    public static $T_String;
    public static $T_char;
    public static $T_chars;
    public static $T_Character;
    public static $T_List;
    public static $T_Set;
    public static $T_Iterator;
    public static $T_Enumeration;
    public static $T_HashMap;
    public static $T_Map;
    public static $T_Dictionary;
}


JavaType::$T_void = JavaType::createPrimitive('void', JavaType::JVM_VOID);
JavaType::$T_boolean = JavaType::createPrimitive('boolean', JavaType::JVM_BOOLEAN);
JavaType::$T_byte = JavaType::createPrimitive('byte', JavaType::JVM_BYTE);
JavaType::$T_char = JavaType::createPrimitive('char', JavaType::JVM_CHAR);
JavaType::$T_short = JavaType::createPrimitive('short', JavaType::JVM_SHORT);
JavaType::$T_int = JavaType::createPrimitive('int', JavaType::JVM_INT);
JavaType::$T_long = JavaType::createPrimitive('long', JavaType::JVM_LONG);
JavaType::$T_float = JavaType::createPrimitive('float', JavaType::JVM_FLOAT);
JavaType::$T_double = JavaType::createPrimitive('double', JavaType::JVM_DOUBLE);

JavaType::$T_Boolean = JavaType::createClass('java.lang.Boolean');
JavaType::$T_Byte = JavaType::createClass('java.lang.Byte');
JavaType::$T_Character = JavaType::createClass('java.lang.Character');
JavaType::$T_Short = JavaType::createClass('java.lang.Short');
JavaType::$T_Integer = JavaType::createClass('java.lang.Integer');
JavaType::$T_Long = JavaType::createClass('java.lang.Long');
JavaType::$T_Float = JavaType::createClass('java.lang.Float');
JavaType::$T_Double = JavaType::createClass('java.lang.Double');
JavaType::$T_String = JavaType::createClass('java.lang.String');
JavaType::$T_List = JavaType::createClass('java.util.List');
JavaType::$T_Set = JavaType::createClass('java.util.Set');
JavaType::$T_Iterator = JavaType::createClass('java.util.Iterator');
JavaType::$T_Enumeration = JavaType::createClass('java.util.Enumeration');
JavaType::$T_HashMap = JavaType::createClass('java.util.HashMap');
JavaType::$T_Map = JavaType::createClass('java.util.Map');
JavaType::$T_Dictionary = JavaType::createClass('java.util.Dictionary');

JavaType::$T_chars = JavaType::createArray(JavaType::$T_char);