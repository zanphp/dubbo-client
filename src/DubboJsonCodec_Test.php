<?php

namespace ZanPHP\Dubbo;

function convertEnum(&$args)
{
    if (is_array($args) && $args) {
        if (isset($args["__enum"])) {
            $args = strval($args["name"]);
        } else {
            foreach ($args as &$arg) {
                convertEnum($arg);
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
                convertEnum($val);
            }
        }
    }
}


class FooEnum {
    private $name = "testPrivate";
    private $__enum = true;

    /**
     * type:0, desc:非赠品
     */
    const NON_PRESENT = ["name" => "NON_PRESENT", "__type" => "com.youzan.trade.core.service.constant.PresentTypeEnum", "__enum" => true];
    /**
     * type:1, desc:满赠
     */
    const MAN_PRESENT = ["name" => "MAN_PRESENT", "__type" => "com.youzan.trade.core.service.constant.PresentTypeEnum", "__enum" => true];
    /**
     * type:4, desc:纯赠品
     */
    const PURE_PRESENT = ["name" => "PURE_PRESENT", "__type" => "com.youzan.trade.core.service.constant.PresentTypeEnum", "__enum" => true];
}

$args = [
    "arg0" => 1,
    "arg2" => ["__enum" => true, "name" => "male"],
    "arg3" => [
        ["__enum" => true, "name" => "male"],
        ["__enum" => true, "name" => "female"]
    ],
    "arg4" => FooEnum::MAN_PRESENT,
    "arg5" => [
        "foo" => FooEnum::NON_PRESENT,
        "bar" => FooEnum::PURE_PRESENT,
        "private" => new FooEnum(),
    ],
];

convertEnum($args);

print_r($args);