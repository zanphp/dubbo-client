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

    public function serialize()
    {
        $serialize = $this->type->getSerialize();
        return $serialize($this->value);
    }
}