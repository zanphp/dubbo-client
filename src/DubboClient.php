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
use ZanPHP\Exception\System\InvalidArgumentException;
use ZanPHP\Log\Log;
use ZanPHP\NovaConnectionPool\NovaConnection;
use ZanPHP\Timer\Timer;
use Thrift\Exception\TApplicationException;


class DubboClient implements Async, Heartbeatable
{
    const DEFAULT_SEND_TIMEOUT = 3000;

    const GENERIC_METHOD = '$invoke';

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

    /**
     * @var ClientContext[]
     */
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

    /**
     * 泛化调用(不能用于重载方法调用)
     *
     * @param string $method
     * @param array $arguments
     * @param int $timeout
     * @return \Generator
     * @throws DubboCodecException
     * @throws InvalidArgumentException
     * @throws \Throwable
     *
     * method         方法名，如：findPerson，如果有重载方法，需带上参数列表，如：findPerson(java.lang.String)
     * parameterTypes 参数类型
     * args           参数列表
     *
     * Object $invoke(String method, String[] parameterTypes, Object[] args) throws GenericException;
     *
     * Ljava/lang/String;[Ljava/lang/String;[Ljava/lang/Object;
     */
    public function genericCall($method, array $arguments, $timeout = self::DEFAULT_SEND_TIMEOUT)
    {
        $method = new JavaValue(JavaType::$T_String, $method);
        $types = new JavaValue(JavaType::$T_Strings, []);
        $args = new JavaValue(JavaType::$T_Objects, $arguments);

        yield setRpcContext("interface", $this->serviceName);
        yield setRpcContext("generic", "true");
        yield $this->call(self::GENERIC_METHOD, [$method, $types, $args], null, $timeout);
    }

    /**
     * 泛化调用(需要提供类型信息, 可用于重载方法)
     *
     * @param string $method
     * @param JavaValue[] $arguments
     * @param int $timeout
     * @return \Generator
     * @throws DubboCodecException
     * @throws InvalidArgumentException
     * @throws \Throwable
     *
     * method         方法名，如：findPerson，如果有重载方法，需带上参数列表，如：findPerson(java.lang.String)
     * parameterTypes 参数类型
     * args           参数列表
     *
     * Object $invoke(String method, String[] parameterTypes, Object[] args) throws GenericException;
     *
     * Ljava/lang/String;[Ljava/lang/String;[Ljava/lang/Object;
     */
    public function genericCallEx($method, array $arguments, $timeout = self::DEFAULT_SEND_TIMEOUT)
    {
        $types = [];
        $args = [];
        foreach ($arguments as $argument) {
            if (!($argument instanceof JavaValue)) {
                throw new InvalidArgumentException();
            }
            $types[] = $argument->getType()->getName();
            $args[] = $argument->getValue();
        }

        $method = new JavaValue(JavaType::$T_String, $method);
        $types = new JavaValue(JavaType::$T_Strings, $types);
        $args = new JavaValue(JavaType::$T_Objects, $args);

        yield setRpcContext("interface", $this->serviceName);
        yield setRpcContext("generic", "true");
        yield $this->call(self::GENERIC_METHOD, [$method, $types, $args], null, $timeout);
    }

    /**
     * @param string $method
     * @param JavaValue[] $arguments
     * @param JavaType|null $returnType
     * @param int $timeout
     * @return \Generator
     * @throws DubboCodecException
     * @throws InvalidArgumentException
     * @throws \Throwable attachment 通过 RpcContext 传递
     */
    public function call($method, array $arguments, JavaType $returnType = null, $timeout = self::DEFAULT_SEND_TIMEOUT)
    {
        $parameterTypes = [];
        foreach ($arguments as $argument) {
            if (!($argument instanceof JavaValue)) {
                throw new InvalidArgumentException();
            }
            $parameterTypes[] = $argument->getType();
        }

        $seq = nova_get_sequence();
        $attachment = (yield getRpcContext(null, []));

        $context = new ClientContext();
        $context->setArguments($arguments);
        $context->setReqServiceName($this->serviceName);
        $context->setReqMethodName($method);
        $context->setReturnType($returnType);
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

        $invoke = new RpcInvocation();
        $invoke->setVersion(DubboCodec::DUBBO_VERSION);
        $invoke->setServiceName($this->serviceName);
        $invoke->setMethodVersion("0.0.0");
        $invoke->setMethodName($method);
        $invoke->setParameterTypes($parameterTypes);
        $invoke->setArguments($arguments);
        $invoke->setAttachments($attachment ?: []);

        $req = new Request();
        $req->setTwoWay(true);
        $req->setData($invoke);
        $req->setId($seq);


        $ex = null;
        try {
            /** @var Codec $codec */
            $codec = new DubboCodec();
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
        /** @var Response $resp */
        $codec = new DubboCodec();
        $resp = $codec->decode($data, function($reqId) {
            $context = isset(self::$reqMap[$reqId]) ? self::$reqMap[$reqId] : null;
            return $context ? $context->getReturnType() : null;
        });

        // dubbo 是双向心跳
        if ($resp instanceof Request) {
            if ($resp->isHeartbeat()) {
                $this->pong($resp);
            }
            return;
        }

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
        $args = $context->getArguments();

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
        $codec = new DubboCodec();
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

    private function pong(Request $resp)
    {
        // FIXME codec ...
        static $pong;
        if ($pong === null) {
            $pong = hex2bin("dabb22140000000000000002000000014e");
        }
        $this->swooleClient->send($pong);
    }
}