<?php

namespace ZanPHP\Dubbo;


final class JavaGenericType
{
    /**
     * @var JavaType
     */
    private $rawType;

    /**
     * @var JavaGenericType[]
     */
    private $genericTypes;

    /**
     * @return JavaType
     */
    public function getRawType()
    {
        return $this->rawType;
    }

    /**
     * @return JavaGenericType[]
     */
    public function getGenericTypes()
    {
        return $this->genericTypes;
    }

    /**
     * @param string $rawType
     */
    public function setRawType($rawType)
    {
        $this->rawType = JavaType::name2type($rawType);
    }

    /**
     * @param JavaGenericType $genericType
     */
    public function addGenericType(JavaGenericType $genericType)
    {
        $this->genericTypes[] = $genericType;
    }
}