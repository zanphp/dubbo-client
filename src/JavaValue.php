<?php

namespace ZanPHP\Dubbo;


class JavaValue
{
    /**
     * @var JavaType
     */
    public $type;
    public $value;

    /**
     * JavaValue constructor.
     * @param JavaType|string $typeOrName java正常书写类型(需要提前注册) 或者 JavaType
     * @param $value
     */
    public function __construct($typeOrName, $value)
    {
        $type = null;
        if ($typeOrName instanceof JavaType) {
            $type = $typeOrName;
        } else {
            $type = JavaType::getByName($typeOrName);
        }

        if (!$type) {
            throw new \InvalidArgumentException("java type $typeOrName still not registed");
        }

        $this->type = $type;
        $this->value = $type->valid($value);
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