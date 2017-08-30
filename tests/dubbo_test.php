<?php

namespace ZanPHP\HessianLite;

require __DIR__ . "/../vendor/autoload.php";


//$ping = hex2bin("dabbe200000000000000000d000000014e");
//$r = unpack('nmagic/Cflag/Cstatus/JreqId/NbodySz', substr($ping, 0, 16));
//var_dump($r);
//var_dump(substr($ping, 16));
//exit;



//$server = new DubboServer("127.0.0.1", 8888);
//$server->start();


















//    private $magic;
//    private $flag;
//    private $status;
//    private $reqId;
//    private $bodySize;
//
//    public function __construct($hdrBin)
//{

//    }
//}

//    private function check()
//    {
//        if ($this->magic !== DubboClient::MAGIC) {
//
//        }
//        if ($this->flag & DubboClient::REQUEST) {
//
//        }
//    }










$request = new Request();
$request->setVersion("2.0.0");
$request->setTwoWay(true);
$request->setData($request);



class DubboServer
{
    private $swooleServer;

    public function __construct($host, $port)
    {
        /** @noinspection PhpUndefinedConstantInspection */
        $this->swooleServer = new \swoole_server($host, $port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);

        $this->swooleServer->set([
            "dispatch_mode" => 3,
            "open_tcp_nodelay" => 1,
            "open_cpu_affinity" => 1,
            "reactor_num" => 2,
            "worker_num" => 1,
            "max_request" => 100000,


            // dubbo protocol --> array_merge($conf, $dubboConf)
            "open_length_check" => true,
            "package_length_type" => "N",
            "package_length_offset" => 12, // 0xdabb + flag(2bytes) + 1bytes + id(8bytes) + 4bytes(body_len)
            "package_body_offset" => 16, // 固定16byte包头
            "package_max_length" => 1024 * 1024 * 2,
        ]);
    }

    public function start()
    {
        $this->swooleServer->on('start', [$this, 'onStart']);
        $this->swooleServer->on('shutdown', [$this, 'onShutdown']);

        $this->swooleServer->on('workerStart', [$this, 'onWorkerStart']);
        $this->swooleServer->on('workerStop', [$this, 'onWorkerStop']);
        $this->swooleServer->on('workerError', [$this, 'onWorkerError']);

        $this->swooleServer->on('connect', [$this, 'onConnect']);
        $this->swooleServer->on('receive', [$this, 'onReceive']);

        $this->swooleServer->on('close', [$this, 'onClose']);

        $this->swooleServer->start();
    }

    public function onConnect()
    {
    }

    public function onClose()
    {
    }

    public function onStart(\swoole_server $swooleServer)
    {
    }

    public function onShutdown(\swoole_server $swooleServer)
    {
    }

    public function onWorkerStart(\swoole_server $swooleServer, $workerId)
    {
        if ($workerId == 0) {
            swoole_timer_after(5000, function () {
                $this->swooleServer->shutdown();
            });
        }
    }

    public function onWorkerStop(\swoole_server $swooleServer, $workerId)
    {
    }

    public function onWorkerError(\swoole_server $swooleServer, $workerId, $workerPid, $exitCode, $sigNo)
    {
        echo "worker error happening [workerId=$workerId, workerPid=$workerPid, exitCode=$exitCode, signalNo=$sigNo]...", "\n";
    }

    public function onReceive(\swoole_server $swooleServer, $fd, $fromId, $data)
    {

    }
}
