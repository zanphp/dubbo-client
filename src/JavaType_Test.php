<?php

namespace ZanPHP\Dubbo;


require __DIR__ . "/JavaType.php";

assert(JavaType::createArray(JavaType::$T_Map) === JavaType::createArray(JavaType::$T_Map));
assert(JavaType::createArray(JavaType::$T_chars) === JavaType::createArray(JavaType::$T_chars));

assert(JavaType::type2desc(JavaType::$T_double) === "D");
assert(JavaType::desc2type("D") === JavaType::$T_double);

assert(JavaType::type2desc(JavaType::$T_Objects) === "[Ljava/lang/Object");
assert(JavaType::desc2type("[Ljava/lang/Object") === JavaType::$T_Objects);

assert(JavaType::type2desc(JavaType::createArray(JavaType::$T_Objects)) === "[[Ljava/lang/Object");
assert(JavaType::desc2type("[[Ljava/lang/Object") === JavaType::createArray(JavaType::$T_Objects));

assert(JavaType::types2desc([JavaType::$T_String, JavaType::$T_Strings, JavaType::$T_Objects]) === "Ljava/lang/String;[Ljava/lang/String;[Ljava/lang/Object;");
assert(JavaType::descs2type("Ljava/lang/String;[Ljava/lang/String;[Ljava/lang/Object;") === [JavaType::$T_String, JavaType::$T_Strings, JavaType::$T_Objects]);

assert(JavaType::name2type("boolean") === JavaType::$T_boolean);
assert(JavaType::type2name(JavaType::$T_boolean) === "boolean");

assert(JavaType::name2type("java.lang.Boolean") === JavaType::$T_Boolean);
assert(JavaType::type2name(JavaType::$T_Boolean) === "java.lang.Boolean");

assert(JavaType::name2type("java.util.Map[][]") === JavaType::createArray(JavaType::createArray(JavaType::$T_Map)));
assert(JavaType::type2name(JavaType::createArray(JavaType::createArray(JavaType::$T_Map))) === "java.util.Map[][]");

assert(JavaType::name2desc("java.util.Map") === "Ljava/util/Map;");
assert(JavaType::desc2name("Ljava/util/Map;") === "java.util.Map");

assert(JavaType::name2desc("java.util.Map[][]") === "[[Ljava/util/Map;");
assert(JavaType::desc2name("[[Ljava/util/Map;") === "java.util.Map[][]");