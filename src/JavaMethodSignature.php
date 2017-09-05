<?php

namespace ZanPHP\Dubbo;


class JavaMethodSignature
{
    private $signature;
    private $methodName;
    private $parameterTypes;
    private $returnType;

    public function __construct($signature)
    {
        $this->signature = $signature;
        $this->parse();
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }

    /**
     * @return JavaGenericType[]
     */
    public function getParameterTypes()
    {
        return $this->parameterTypes;
    }

    /**
     * @return JavaGenericType
     */
    public function getReturnType()
    {
        return $this->returnType;
    }

    public function parse()
    {
        $signature = $this->signature;
        $i = strpos($signature, "(");
        $this->methodName = substr($signature, 0, $i);

        $j = strpos($signature, ")");
        $ptypes = $this->explode0(substr($signature,$i + 1, $j - $i - 1));
        foreach($ptypes as $i => $ptype) {
            $ptypes[$i] = self::parseType($ptype);
        }
        $this->parameterTypes = $ptypes;

        $this->returnType = self::parseType(substr($signature, $j + 1));
    }

    public static function parseType($type)
    {
        $gt = new JavaGenericType();
        $i = strpos($type, "<");
        if ($i === false) {
            $gt->setRawType($type);
        } else {
            $raw = substr($type, 0, $i);
            $gt->setRawType($raw);
            $generics = self::explode0(substr($type, $i + 1, -1));
            foreach ($generics as $generic_) {
                $generic = self::parseType(trim($generic_));
                $gt->addGenericType($generic);
            }
        }
        return $gt;
    }

    private static function explode0($types, $sep = ",") {
        $seps = [];
        $l = strlen($types);
        if ($l === 0) {
            return [];
        }

        $n = 0;
        for ($i = 0; $i < $l; ) {
            if ($n === 0 && $types[$i] === $sep) {
                $seps[] = $i;
            } else if ($types[$i] === "<") {
                $n++;
            } else if ($types[$i] === ">") {
                $n--;
            }
            $i++;
        }
        if ($n !== 0) {
            throw new \InvalidArgumentException("Invalid Java Generic Type In: $types");
        }
        $r = [];
        $i = 0;
        foreach ($seps as $sep) {
            $r[] = substr($types, $i, $sep - $i);
            $i = $sep + 1;
        }
        $r[] = substr($types, $i);
        return $r;
    }
}