<?php

namespace ZanPHP\Dubbo;


class JavaValue
{
    /**
     * @var JavaType
     */
    private $type;

    private $value;

    /**
     * JavaValue constructor.
     * @param JavaType|string $name ~java类型字符串表示 或者 JavaType~
     * @param $value
     */
    public function __construct($name, $value)
    {
        if ($name instanceof JavaType) {
            $this->type = $name;
        } else {
            $this->type = JavaType::name2type($name);
        }
        $this->setValue($value);
    }

    /**
     * @return JavaType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $this->type->valid($value);
    }

    // FIXME dubbo/hessian-lite/src/main/java/com/alibaba/com/caucho/hessian/io/SerializerFactory.java
    public function serialize()
    {
        $serialization = $this->type->getSerialization();
        if (!$serialization) {
            $serialization = CodecSupport::getJavaTypeDefaultSerialization($this->type);
            $this->type->setSerialization($serialization);
        }
        return $serialization($this->value);
    }
}