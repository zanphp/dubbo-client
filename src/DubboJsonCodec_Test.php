<?php

namespace ZanPHP\Dubbo;


function isJSON($string)
{
    if (!is_string($string)) {
        return false;
    }

    $pcreRegex = '
  /
  (?(DEFINE)
     (?<number>   -? (?= [1-9]|0(?!\d) ) \d+ (\.\d+)? ([eE] [+-]? \d+)? )    
     (?<boolean>   true | false | null )
     (?<string>    " ([^"\\\\]* | \\\\ ["\\\\bfnrt\/] | \\\\ u [0-9a-f]{4} )* " )
     (?<array>     \[  (?:  (?&json)  (?: , (?&json)  )*  )?  \s* \] )
     (?<pair>      \s* (?&string) \s* : (?&json)  )
     (?<object>    \{  (?:  (?&pair)  (?: , (?&pair)  )*  )?  \s* \} )
     (?<json>   \s* (?: (?&number) | (?&boolean) | (?&string) | (?&array) | (?&object) ) \s* )
  )
  \A (?&json) \Z
  /six   
';
    return boolval(preg_match($pcreRegex, $string));
}

assert(isJSON(file_get_contents("http://www.json.org/JSON_checker/test/pass1.json")));
assert(isJSON(file_get_contents("http://www.json.org/JSON_checker/test/pass2.json")));
assert(isJSON(file_get_contents("http://www.json.org/JSON_checker/test/pass3.json")));


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