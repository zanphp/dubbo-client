<?php

namespace ZanPHP\Dubbo;

use ZanPHP\Contracts\ConnectionPool\Heartbeatable;
use ZanPHP\Contracts\Trace\Trace;
use ZanPHP\Contracts\ConnectionPool\Connection;
use ZanPHP\Contracts\Codec\Codec;
use ZanPHP\Contracts\Config\Repository;
use ZanPHP\Contracts\Debugger\Tracer;
use ZanPHP\Contracts\Hawk\Hawk;
use ZanPHP\Contracts\Trace\Constant;
use ZanPHP\Coroutine\Contract\Async;
use ZanPHP\Coroutine\Task;
use ZanPHP\Dubbo\Exception\ClientErrorException;
use ZanPHP\Dubbo\Exception\ClientTimeoutException;
use ZanPHP\Dubbo\Exception\DubboCodecException;
use ZanPHP\Log\Log;
use ZanPHP\NovaConnectionPool\NovaConnection;
use ZanPHP\Timer\Timer;
use Thrift\Exception\TApplicationException;


class DubboClient implements Async, Heartbeatable
{
    const DEFAULT_SEND_TIMEOUT = 3000;

    /**
     * @var NovaConnection
     */
    private $dubboConnection;

    /**
     * @var \swoole_client
     */
    private $swooleClient;

    private $serviceName;

    /**
     * @var ClientContext
     */
    private $currentContext;

    private $serverAddr;

    private static $reqMap = [];

    private static $instance = null;

    private static $sendTimeout;

    private static $seqTimerId = [];

    /**
     * @param Connection $conn
     * @param $serviceName
     * @return static
     */
    public static function getInstance(Connection $conn, $serviceName)
    {
        $key = spl_object_hash($conn) . '_' . $serviceName;
        if (!isset(static::$instance[$key]) || null === static::$instance[$key]) {
            static::$instance[$key] = new static($conn, $serviceName);
            if (self::$sendTimeout === null) {
                /** @var Repository $repository */
                $repository = make(Repository::class);
                $defaultTimeout = $repository->get("connection.dubbo.send_timeout", static::DEFAULT_SEND_TIMEOUT);
                self::$sendTimeout = $defaultTimeout;
            }
        }

        return static::$instance[$key];
    }

    public function __construct(Connection $conn, $serviceName)
    {
        $this->serviceName = $serviceName;
        $this->dubboConnection = $conn;
        $this->swooleClient = $conn->getSocket();
        $this->dubboConnection->setOnReceive([$this, "onReceive"]);

        $sockInfo = $this->swooleClient->getsockname();
        $this->serverAddr = ip2long($sockInfo["host"]) . ':' . $sockInfo["port"];
    }

    public function execute(callable $callback, $task)
    {
        $this->currentContext->setCb($callback);
        $this->currentContext->setTask($task);
    }

    public function call($method, $inputArguments, $outputStruct, $exceptionStruct, $timeout = null)
    {
        $seq = nova_get_sequence();
        $attachment = (yield getRpcContext(null, []));

        $context = new ClientContext();
        $context->setInputArguments($inputArguments);
        $context->setOutputStruct($outputStruct);
        $context->setExceptionStruct($exceptionStruct);
        $context->setReqServiceName($this->serviceName);
        $context->setReqMethodName($method);
        $context->setReqSeqNo($seq);
        $context->setStartTime();
        $context->setHawk(make(Hawk::class));
        $context->setTrace((yield getContext('trace')));
        $context->setDebuggerTrace((yield getContext("debugger_trace")));

        yield $this->beginTransaction($context, $attachment);

        if ($attachment) {
            $attachment[Trace::TRACE_KEY] = json_encode($attachment[Trace::TRACE_KEY]);
        }
        $this->currentContext = $context;

        // FIXME
        // $thriftBin = $packer->encode(TMessageType::CALL, $method, $inputArguments, Packer::CLIENT);

        $invoke = new RpcInvocation();
        $invoke->setDubboVersion(DubboCodec::DUBBO_VERSION);
        $invoke->setServiceName($this->serviceName);
        $invoke->setMethodVersion("0.0.0");
        $invoke->setMethodName($method);
        // FIXME
        $invoke->setParameterTypes([JavaType::$T_String]);
        $invoke->setArguments([new JavaValue(JavaType::$T_String, "world")]);
        // FIXME  $attachmentContent = json_encode($attachment ?: new \stdClass());
        $invoke->setAttachments($attachment ?: []);

        $req = new Request();
        $req->setTwoWay(true);
        $req->setData($invoke);
        $req->setId($seq);

        /** @var Codec $codec */
        $codec = make("codec:dubbo");

        $ex = null;
        try {
            $sendBuffer = $codec->encode($req);
            $this->dubboConnection->setLastUsedTime();
            $r = $this->swooleClient->send($sendBuffer);
            if (!$r) {
                $ex = new DubboCodecException("fail encoding request");
            }
        } catch (\Throwable $ex) {
        } catch (\Exception $ex) {}

        if ($ex) {
            yield $this->onCallFail($context, $ex);
            throw $ex;
        } else {
            self::$reqMap[$seq] = $context;
            $timeout = $timeout ?: self::$sendTimeout;
            $timerId = Timer::after($timeout, function() use($context) {
                $this->onCallTimeout($context);
            });
            self::$seqTimerId[$seq] = $timerId;
            yield $this;
        }
    }

    public function onReceive($data)
    {
        if ($data === false || $data === "") {
            $this->closeConnection($this->getClientErrorException());
            return;
        }

        /** @var Codec $codec */
        $codec = make("codec:dubbo");
        /** @var Response $resp */
        $resp = $codec->decode($data);
        if (!($resp instanceof Response)) {
            return;
        }

        $seqNo = $resp->getId();
        if (isset(self::$seqTimerId[$seqNo])) {
            Timer::clearAfterJob(self::$seqTimerId[$seqNo]);
            unset(self::$seqTimerId[$seqNo]);
        }

        /** @var ClientContext $context */
        $context = isset(self::$reqMap[$seqNo]) ? self::$reqMap[$seqNo] : null;
        if (!$context) {
            return;
        }
        unset(self::$reqMap[$seqNo]);

        if ($resp->isHeartbeat()) {
            call_user_func($context->getCb(), true);
            return;
        }

        if ($resp->isOK()) {
            $rpcResult = $resp->getResult();
            if (!($rpcResult instanceof RpcResult)) {
                return;
            }
            $bizEx = $rpcResult->getException();
            $bizRet = $rpcResult->getValue(); // FIXME unpack rpcResult
            $this->endTransaction($context, $bizRet, $bizRet);
            call_user_func($context->getCb(), $bizRet, $bizEx);
        } else {
            $rpcEx = $resp->getException();
            $this->endTransaction($context, null, $rpcEx);
            call_user_func($context->getCb(), null, $rpcEx);
        }
    }

    private function getClientErrorException()
    {
        $msg = socket_strerror($this->swooleClient->errCode);
        $code = $this->swooleClient->errCode;
        return new ClientErrorException($msg, $code);
    }

    private function onCallTimeout(ClientContext $context)
    {
        $ex = new ClientTimeoutException("dubbo recv timeout");
        $seq = $context->getReqSeqNo();
        unset(self::$reqMap[$seq]);
        unset(self::$seqTimerId[$seq]);

        $this->endTransaction($context, null, $ex);
        $cb = $this->currentContext->getCb();
        call_user_func($cb, null, $ex);
    }

    private function onCallFail(ClientContext $context, $exception)
    {
        $serviceName = $context->getReqServiceName();
        $methodName = $context->getReqMethodName();

        /** @var Hawk $hawk */
        $hawk = make(Hawk::class);

        $hawk->addTotalFailureTime(Hawk::CLIENT, $serviceName, $methodName, $this->serverAddr, microtime(true) - $context->getStartTime());
        $hawk->addTotalFailureCount(Hawk::CLIENT, $serviceName, $methodName, $this->serverAddr);

        $trace = (yield getContext('trace'));
        $traceId = '';
        if ($trace instanceof Trace) {
            $trace->commit($context->getTraceHandle(), $exception);
            $traceId = $trace->getRootId();
        }

        $debuggerTrace = (yield getContext("debugger_trace"));
        $debuggerTid = null;
        if ($debuggerTrace instanceof Tracer) {
            $debuggerTrace->commit($debuggerTid, "error", $exception);
        }

        if (make(Repository::class)->get('log.zan_framework')) {
            yield Log::make('zan_framework')->error($exception->getMessage(), [
                'exception' => $exception,
                'app' => getenv("appname"),
                'language'=>'php',
                'side'=>'client',//server,client两个选项
                'traceId'=> $traceId,
                'method'=>"$serviceName.$methodName",
            ]);
        }
    }

    private function closeConnection($exception)
    {
        foreach (self::$reqMap as $req) {
            /** @var Task $task */
            $task = $req->getTask();

            /** @var Trace $trace */
            $trace = $task->getContext()->get('trace');
            $trace->commit($req->getTraceHandle(), socket_strerror($this->swooleClient->errCode));
            $task->sendException($exception);
        }

        $this->dubboConnection->close();
    }

    private function beginTransaction(ClientContext $context, array &$attachment)
    {
        $trace = (yield getContext('trace'));
        $debuggerTrace = (yield getContext("debugger_trace"));

        $serviceName = $context->getReqServiceName();
        $methodName = $context->getReqMethodName();
        $args = $context->getInputArguments();

        if ($trace instanceof Trace) {
            $traceHandle = $trace->transactionBegin(Constant::NOVA_CLIENT, "$serviceName.$methodName");
            $context->setTraceHandle($traceHandle);
            $msgId =  $trace->generateId();
            $trace->logEvent(Constant::REMOTE_CALL, Constant::SUCCESS, "", $msgId);
            $trace->setRemoteCallMsgId($msgId);
            if ($trace->getRootId()) {
                $attachment[Trace::TRACE_KEY]['rootId'] = $attachment[Trace::TRACE_KEY][Trace::ROOT_ID_KEY] = $trace->getRootId();
            }
            if ($trace->getParentId()) {
                $attachment[Trace::TRACE_KEY]['parentId'] = $attachment[Trace::TRACE_KEY][Trace::PARENT_ID_KEY] = $trace->getParentId();
            }
            $attachment[Trace::TRACE_KEY]['eventId'] = $attachment[Trace::TRACE_KEY][Trace::CHILD_ID_KEY] = $msgId;
        }

        if ($debuggerTrace instanceof Tracer) {
            $debuggerTid = $debuggerTrace->beginTransaction(Constant::NOVA_CLIENT, "$serviceName.$methodName", $args);
            $context->setDebuggerTraceTid($debuggerTid);
            $attachment[Tracer::KEY] = $debuggerTrace->getKey();
        }
    }


    private function endTransaction(ClientContext $context, $result = null, $e = null)
    {
        $trace = $context->getTrace();
        $debuggerTrace = $context->getDebuggerTrace();
        $hawk = $context->getHawk();
        $serviceName = $context->getReqServiceName();
        $methodName = $context->getReqMethodName();

        if ($e) {
            if ($hawk instanceof Hawk) {
                //只有系统异常上报异常信息
                if ($e instanceof TApplicationException) {
                    $hawk->addTotalFailureTime(Hawk::CLIENT, $serviceName, $methodName, $this->serverAddr, microtime(true) - $context->getStartTime());
                    $hawk->addTotalFailureCount(Hawk::CLIENT, $serviceName, $methodName, $this->serverAddr);
                } else {
                    $hawk->addTotalSuccessTime(Hawk::CLIENT, $serviceName, $methodName, $this->serverAddr, microtime(true) - $context->getStartTime());
                    $hawk->addTotalSuccessCount(Hawk::CLIENT, $serviceName, $methodName, $this->serverAddr);
                }
            }

            if ($trace instanceof Trace) {
                if ($e instanceof TApplicationException) {
                    $trace->commit($context->getTraceHandle(), $e->getTraceAsString());
                } else {
                    $trace->commit($context->getTraceHandle(), Constant::SUCCESS);
                }
            }

            if ($debuggerTrace instanceof Tracer) {
                $debuggerTrace->commit($context->getDebuggerTraceTid(), "error", $e);
            }
        } else {
            if ($hawk instanceof Hawk) {
                $hawk->addTotalSuccessTime(Hawk::CLIENT, $serviceName, $methodName, $this->serverAddr, microtime(true) - $context->getStartTime());
                $hawk->addTotalSuccessCount(Hawk::CLIENT, $serviceName, $methodName, $this->serverAddr);
            }

            if ($trace instanceof Trace) {
                $trace->commit($context->getTraceHandle(), Constant::SUCCESS);
            }

            if ($debuggerTrace instanceof Tracer) {
                $debuggerTrace->commit($context->getDebuggerTraceTid(), "info", $result);
            }
        }
    }

    public function ping()
    {
        /** @var int $seq */
        $seq = nova_get_sequence();

        $context = new ClientContext();
        $context->setReqServiceName($this->serviceName);
        $context->setReqMethodName("ping");
        $context->setReqSeqNo($seq);

        $this->currentContext = $context;

        /** @var Codec $codec */
        $codec = make("codec:nova");
        $pdu = new Request();
        $pdu->setVersion(DubboCodec::DUBBO_VERSION);
        $pdu->setTwoWay(true);
        $pdu->setData(null);
        $pdu->setEvent(Request::HEARTBEAT_EVENT);
        $pdu->setId($seq);

        try {
            $sendBuffer = $codec->encode($pdu);
            $this->dubboConnection->setLastUsedTime();
            $this->swooleClient->send($sendBuffer);
            self::$reqMap[$seq] = $context;
            Timer::after(self::$sendTimeout, function() use($seq) {
                unset(self::$reqMap[$seq]);
            });
            yield $this;
        } catch (\Throwable $t) {
            echo_exception($t);
        } catch (\Exception $e) {
            echo_exception($e);
        }
    }
}