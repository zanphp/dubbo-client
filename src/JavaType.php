<?php

namespace ZanPHP\Dubbo;

use ZanPHP\Exception\System\ClassNotFoundException;


/**
 * Class JavaType
 *      Java 类型系统表示(无泛型), 每种类型的type仅有一份, 可以直接比较对象
 * @package ZanPHP\Dubbo
 */
class JavaType
{
    /**
     * JVM_* Java类型的字节码表示格式
     */

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

    /**
     * Java 类型字符串表示
     *      void, int, boolean, char[], ...
     *      java.lang.String
     *      java.lang.Object[][]
     *
     * @var string
     */
    private $name;

    /**
     * 是否是Java原生类型
     * @var bool
     */
    private $isPrimitive = false;

    private $isArray = false;

    private $isEnum = false;

    /**
     * 数组元素类型
     * @var JavaType
     */
    private $componentClass;

    /**
     * 字节码格式的类型表示, 用户dubbo协议
     * @var
     */
    private $desc;

    /**
     * FIXME 补全内置类型 类型校验函数
     * 注册到类型的校验函数, 用来校验该类型的value
     * @var callable
     */
    private $valid;

    /**
     * FIXME 补全内类型 类型序列化函数
     * 注册到类型的序列化函数, 用来序列化该类型value
     * @var callable
     */
    private $serialize;

    /**
     * @var callable
     */
    private $unserialize;

    public function isArray()
    {
        return $this->isArray;
    }

    public function isEnum()
    {
        return $this->isEnum;
    }

    public function isPrimitive()
    {
        return $this->isPrimitive;
    }

    public function getComponentType()
    {
        return $this->componentClass;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDesc()
    {
        return $this->desc;
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

    public function getSerialize()
    {
        if (!$this->serialize) {
            $this->serialize = CodecSupport::getJavaTypeDefaultSerialize($this);
        }

        return $this->serialize;
    }

    public function setSerialize(callable $serialize)
    {
        $this->serialize = $serialize;
    }

    public function getUnserialize()
    {
        if (!$this->unserialize) {
            $this->unserialize = CodecSupport::getJavaTypeDefaultUnSerialize($this);
        }
        return $this->unserialize;
    }

    public function setUnserialize(callable $unserialize)
    {
        $this->unserialize = $unserialize;
    }

    public function __toString()
    {
        return $this->name;
    }

    private static $NAME_TO_TYPE = [];

    private static function register(JavaType $type)
    {
        static::$NAME_TO_TYPE[$type->getName()] = $type;
    }

    /**
     * @param $name
     * @return static|null
     */
    private static function tryGetTypeByName($name)
    {
        if (!isset(static::$NAME_TO_TYPE[$name])) {
            return null;
        }
        return static::$NAME_TO_TYPE[$name];
    }

    public static function createPrimitive($name, $desc)
    {
        $type = self::tryGetTypeByName($name);
        if ($type) {
            assert($type->isPrimitive());
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
        $type = self::tryGetTypeByName($class);
        if ($type) {
            assert(!$type->isPrimitive() && !$type->isArray() && !$type->isEnum());
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
        $type = self::tryGetTypeByName($componentClass->getName() . "[]");
        if ($type) {
            assert($type->isArray());
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

    public static function createEnum($class)
    {
        $type = self::tryGetTypeByName($class);
        if ($type) {
            assert($type->isEnum());
            return $type;
        }

        $self = new static;
        $self->name = $class;
        $self->desc = self::JVM_OBJECT . str_replace('.', '/', $class);
        $self->isPrimitive = false;
        $self->isArray = false;
        $self->isEnum = true;
        static::register($self);
        return $self;
    }

    /**
     * @param JavaType $type
     * @return string
     */
    public static function type2name(JavaType $type)
    {
        return $type->name;
    }

    /**
     * @param JavaType $type
     * @return string
     */
    public static function type2desc(JavaType $type)
    {
        return $type->desc;
    }

    /**
     * JavaType[] ==> desc
     *
     * JavaType::createArray(JavaType::$T_boolean) ==> "[Z"
     * JavaType::$T_Object ==> "Ljava/lang/Object;"
     * [JavaType::$T_String, JavaType::$T_Strings, JavaType::$T_Objects]
     *      ==> Ljava/lang/String;[Ljava/lang/String;[Ljava/lang/Object;
     *
     * @param JavaType[] $types
     * @return string
     */
    public static function types2desc(array $types)
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

    /**
     * "boolean" => boolean.class
     * "java.util.Map[][]" ==> JavaType::createArray(JavaType::createArray(JavaType::$T_Map))
     *
     * @param $name
     * @return JavaType
     */
    public static function name2type($name)
    {
        if (strlen($name) === 0) {
            throw new \InvalidArgumentException();
        }

        $type = self::tryGetTypeByName($name);
        if ($type) {
            return $type;
        }

        $c = 0;
        $index = strpos($name, self::JVM_ARRAY);
        if ($index > 0) {
            $c = (strlen($name) - $index) / 2;
            $name = substr($name, 0, $index);
        }
        $arr = str_repeat(self::JVM_ARRAY, $c);

        if ($c === false) {
            switch ($name) {
                case "void":
                    return JavaType::$T_void;
                case "boolean":
                    return JavaType::$T_boolean;
                case "byte":
                    return JavaType::$T_byte;
                case "char":
                    return JavaType::$T_char;
                case "double":
                    return JavaType::$T_double;
                case "float":
                    return JavaType::$T_float;
                case "int":
                    return JavaType::$T_int;
                case "long":
                    return JavaType::$T_long;
                case "short":
                    return JavaType::$T_short;
            }
        }

        switch ($name) {
            case "void":
                $desc = $arr . self::JVM_VOID;
                break;
            case "boolean":
                $desc = $arr . self::JVM_BOOLEAN;
                break;
            case "byte":
                $desc = $arr . self::JVM_BYTE;
                break;
            case "char":
                $desc = $arr . self::JVM_CHAR;
                break;
            case "double":
                $desc = $arr . self::JVM_DOUBLE;
                break;
            case "float":
                $desc = $arr . self::JVM_FLOAT;
                break;
            case "int":
                $desc = $arr . self::JVM_INT;
                break;
            case "long":
                $desc = $arr . self::JVM_LONG;
                break;
            case "short":
                $desc = $arr . self::JVM_SHORT;
                break;
            default:
                $desc = $arr ."L" . str_replace(".", "/", $name) . ";";
        }
        $type = self::desc2type($desc);
        self::register($type);
        return $type;
    }

    /**
     * @param string $name
     * @return string
     */
    public static function name2desc($name)
    {
        if (strlen($name) === 0) {
            throw new \InvalidArgumentException();
        }

        $c = 0;
        $index = strpos($name, self::JVM_ARRAY);
        if ($index > 0) {
            $c = (strlen($name) - $index) / 2;
            $name = substr($name, 0, $index);
        }
        $arr = str_repeat(self::JVM_ARRAY, $c);
        switch ($name) {
            case "void":
                return $arr . self::JVM_VOID;
            case "boolean":
                return $arr . self::JVM_BOOLEAN;
            case "byte":
                return $arr . self::JVM_BYTE;
            case "char":
                return $arr . self::JVM_CHAR;
            case "double":
                return $arr . self::JVM_DOUBLE;
            case "float":
                return $arr . self::JVM_FLOAT;
            case "int":
                return $arr . self::JVM_INT;
            case "long":
                return $arr . self::JVM_LONG;
            case "short":
                return $arr . self::JVM_SHORT;
            default:
                return $arr ."L" . str_replace(".", "/", $name) . ";";
        }
    }

    /**
     * @param string $desc
     * @return string
     * @throws \Exception
     */
    public static function desc2name($desc)
    {
        $desc = rtrim($desc, ';');
        if (strlen($desc) === 0) {
            throw new \InvalidArgumentException();
        }

        $lastIndex = strrpos($desc, self::JVM_ARRAY);
        if ($lastIndex === false) {
            $c = 0;
        } else {
            $c = $lastIndex + 1;
        }
        $buf = "";
        if (strlen($desc) === $c + 1) {
            switch ($desc[$c]) {
                case self::JVM_VOID:
                    $buf .= "void";
                    break;
                case self::JVM_BOOLEAN:
                    $buf .= "boolean";
                    break;
                case self::JVM_BYTE:
                    $buf .= "byte";
                    break;
                case self::JVM_CHAR:
                    $buf .= "char";
                    break;
                case self::JVM_DOUBLE:
                    $buf .= "double";
                    break;
                case self::JVM_FLOAT:
                    $buf .= "float";
                    break;
                case self::JVM_INT:
                    $buf .= "int";
                    break;
                case self::JVM_LONG:
                    $buf .= "long";
                    break;
                case self::JVM_SHORT:
                    $buf .= "short";
                    break;
                default:
                    throw new \Exception();
            }
        } else {
            $buf .= str_replace("/", ".", substr($desc, $c + 1));
        }
        return $buf . str_repeat("[]", $c);
    }

    /**
     * Ljava/lang/Object ==> JavaType::$T_Object
     *
     * @param string $desc
     * @return JavaType
     * @throws \Exception
     */
    public static function desc2type($desc)
    {
        $desc = rtrim($desc, ';');
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
                $type = static::tryGetTypeByName($className);
                if ($type === null) {
                    $type = static::createClass($className);
                }
                return $type;
            case self::JVM_ARRAY:
                $desc = str_replace('/', '.', substr($desc, 1));
                return static::createArray(static::desc2type($desc));
            default:
                throw new ClassNotFoundException("Class not found: $desc");
        }
    }

    /**
     * Ljava/lang/Object;I ==> [JavaType::$T_Object, JavaType:$T_int]
     *
     * @param string $desc
     * @return JavaType[]
     */
    public static function descs2type($desc)
    {
        if (strlen($desc) === 0) {
            return [];
        }

        $types = [];
        if (preg_match_all('#' . self::DESC_REGEX . '#', $desc, $matches)) {
            foreach ($matches[0] as $desc) {
                $types[] = static::desc2type($desc);
            }
        }
        return $types;
    }

    // -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
    public static $T_Unknown;

    public static $T_void;
    public static $T_Void;
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
    public static $T_Object;

    // for generic invoke
    public static $T_Strings;
    public static $T_Objects;
}

JavaType::$T_Unknown = new JavaType();

JavaType::$T_void = JavaType::createPrimitive('void', JavaType::JVM_VOID);
JavaType::$T_boolean = JavaType::createPrimitive('boolean', JavaType::JVM_BOOLEAN);
JavaType::$T_byte = JavaType::createPrimitive('byte', JavaType::JVM_BYTE);
JavaType::$T_char = JavaType::createPrimitive('char', JavaType::JVM_CHAR);
JavaType::$T_short = JavaType::createPrimitive('short', JavaType::JVM_SHORT);
JavaType::$T_int = JavaType::createPrimitive('int', JavaType::JVM_INT);
JavaType::$T_long = JavaType::createPrimitive('long', JavaType::JVM_LONG);
JavaType::$T_float = JavaType::createPrimitive('float', JavaType::JVM_FLOAT);
JavaType::$T_double = JavaType::createPrimitive('double', JavaType::JVM_DOUBLE);

JavaType::$T_Void = JavaType::createClass('java.lang.Void');
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
JavaType::$T_Object = JavaType::createClass('java.lang.Object');

JavaType::$T_chars = JavaType::createArray(JavaType::$T_char);
JavaType::$T_Strings = JavaType::createArray(JavaType::$T_String);
JavaType::$T_Objects = JavaType::createArray(JavaType::$T_Object);