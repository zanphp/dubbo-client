<?php

namespace ZanPHP\Dubbo;


class Client
{
    const NAME = "dubbo-php";
    const DUBBO_VERSION = "0.0.1";

    const HEADER_LENGTH = 16;

    // magic header. int16_t
    const MAGIC = 0xdabb;

    // message flag. int8_t
    const REQUEST = 0x80;
    const TWOWAY = 0x40;
    const EVENT = 0x20;
    const SERIALIZATION_HESSIAN = 2;

    const SERIALIZATION_MASK = 0x1f;

    // int8_t
    const RESPONSE_WITH_EXCEPTION = 0;
    const RESPONSE_VALUE = 1;
    const RESPONSE_NULL_VALUE = 2;


    private $client;
    private $timerId;

    public function __construct()
    {
        /** @noinspection PhpUndefinedConstantInspection */
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        $this->client->set([
            // dubbo protocol --> array_merge($conf, $dubboConf)
            "open_length_check" => true,
            "package_length_type" => "N",
            "package_length_offset" => 12, // 0xdabb + flag(2bytes) + 1bytes + id(8bytes) + 4bytes(body_len)
            "package_body_offset" => self::HEADER_LENGTH, // 固定16byte包头
            "package_max_length" => 1024 * 1024 * 2,
        ]);

        $this->client->on("connect", [$this, "onConnect"]);
        $this->client->on("receive", [$this, "onReceive"]);
        $this->client->on("error", [$this, "onError"]);
        $this->client->on("close", [$this, "onClose"]);
    }

    public function connect($ip, $port, $timeout = 1000)
    {
        if ($this->client->connect($ip, $port)) {
            $this->timerId = swoole_timer_after($timeout, [$this, "onTimeout"]);
            return true;
        }
        return false;
    }

    public function onConnect(\swoole_client $client)
    {
        if (swoole_timer_exists($this->timerId)) {
            swoole_timer_clear($this->timerId);
        }

        echo "connected";


        $req = new Request();

        $invoke = new RpcInvocation();
        $invoke->setAttachment(Constants::DUBBO_VERSION_KEY, DubboCodec::DUBBO_VERSION);
        $invoke->setAttachment(Constants::PATH_KEY, "com.alibaba.dubbo.demo.DemoService");
        $invoke->setAttachment(Constants::VERSION_KEY, "0.0.0");

        $invoke->setMethodName("sayHello");
        $invoke->setParameterTypes([JavaType::$T_String]);
        $invoke->setArguments([new JavaValue(JavaType::$T_String, "world")]);


        // List<Integer> arg1, Map<String, Float> arg2, Long arg3

        $invoke->setMethodName("test");
        $invoke->setParameterTypes([
            JavaType::createArray(JavaType::$T_Integer),
            JavaType::$T_Map,
            JavaType::$T_Long
        ]);
        $invoke->setArguments([
            new JavaValue(JavaType::getByName("java.lang.Integer[]"), [42,1234]),
            new JavaValue(JavaType::$T_Map, ["hello" => "world"]),
            new JavaValue(JavaType::$T_Long, 42)
        ]);

        $req->setVersion(DubboCodec::DUBBO_VERSION);
        $req->setTwoWay(true);
        $req->setData($invoke);

        $codec = new DubboCodec();

        $client->send($codec->encode($req));

        return;
//        $ping = hex2bin("dabbe200000000000000000d000000014e");
//        $client->send($ping);

//        $flag = self::REQUEST | self::SERIALIZATION_HESSIAN | self::TWOWAY | self::EVENT;
        $flag = self::REQUEST | self::SERIALIZATION_HESSIAN | self::TWOWAY;
        $status = 0;
        $reqId = 1;

        $service = "com.alibaba.dubbo.demo.DemoService";
        $serviceVer = "0.0.0";
        $method = "sayHello";

        $writer = Factory::getWriter();
        $body = "";
        $body .= $writer->writeString(self::DUBBO_VERSION);
        $body .= $writer->writeString($service);
        $body .= $writer->writeString($serviceVer);
        $body .= $writer->writeString($method);
        $bodySz = strlen($body);
        $client->send(pack('nCCJN', 0xdabb, $flag, $status, $reqId, $bodySz) . $body);
    }

    public function onError(\swoole_client $client)
    {
        if (swoole_timer_exists($this->timerId)) {
            swoole_timer_clear($this->timerId);
        }
    }

    public function onClose(\swoole_client $client)
    {
        if (swoole_timer_exists($this->timerId)) {
            swoole_timer_clear($this->timerId);
        }
    }

    public function onTimeout()
    {
        $this->client->close();
    }

    public function onReceive(\swoole_client $client, $recv)
    {
        $hdr = unpack('nmagic/Cflag/Cstatus/JreqId/NbodySz', substr($recv, 0, self::HEADER_LENGTH));
        var_dump($hdr);
        var_dump(substr($recv, 16));
        return;



        $pong = hex2bin("dabb22140000000000000002000000014e");
        if ($recv === $pong) {
            return;
        }

        $len = strlen($recv);
        if ($len < self::HEADER_LENGTH) {
            echo "ERROR invalid payload len, ", bin2hex($recv), "\n";
            $client->close();
            return;
        }

        $hdr = unpack('nmagic/Cflag/Cstatus/JreqId/NbodySz', substr($recv, 0, self::HEADER_LENGTH));
        var_dump($hdr);
        $flag = $hdr["flag"];
        assert(($flag & self::REQUEST) == 0);
        if ($flag & self::EVENT) {
            return;
        }
        assert($hdr["bodySz"] + self::HEADER_LENGTH === $len);

        $body = substr($recv, self::HEADER_LENGTH);
        $flag = unpack("Cflag", $body[0])["flag"];
        switch ($flag) {
            case self::RESPONSE_NULL_VALUE:
                break;
            case self::RESPONSE_VALUE:
                break;
            case self::RESPONSE_WITH_EXCEPTION:
                echo $body, "\n";
                break;
            default:
                echo "ERROR invalid flag $flag, ", bin2hex($recv), "\n";
                $client->close();
                return;
        }


//        $pong = hex2bin("dabb22140000000000000002000000014e");
//        assert($r["magic"] === 0xdabb);
//        assert($r["bodySz"] + 16 === strlen($recv));
//        assert($recv[16] === 'N');
    }
}
