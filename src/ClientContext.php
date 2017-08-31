<?php

namespace ZanPHP\Dubbo;

use ZanPHP\Coroutine\Task;

class ClientContext
{
    private $reqServiceName;
    private $reqMethodName;
    private $reqSeqNo;
    private $cb;
    private $task;
    private $startTime;//us
    private $traceHandle;
    private $debuggerTraceTid;
    private $trace;
    private $debuggerTrace;
    private $hawk;
    private $arguments;


    /**
     * @return Task
     */
    public function getTask()
    {
        return $this->task;
    }

    public function setTask($task)
    {
        $this->task = $task;
    }

    public function getCb()
    {
        return $this->cb;
    }

    public function setCb($cb)
    {
        $this->cb = $cb;
    }

    public function getReqServiceName()
    {
        return $this->reqServiceName;
    }

    public function setReqServiceName($reqServiceName)
    {
        $this->reqServiceName = $reqServiceName;
    }

    public function getReqMethodName()
    {
        return $this->reqMethodName;
    }

    public function setReqMethodName($reqMethodName)
    {
        $this->reqMethodName = $reqMethodName;
    }

    public function getReqSeqNo()
    {
        return $this->reqSeqNo;
    }

    public function setReqSeqNo($reqSeqNo)
    {
        $this->reqSeqNo = $reqSeqNo;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function setStartTime()
    {
        $this->startTime = microtime(true);
    }

    public function getTraceHandle()
    {
        return $this->traceHandle;
    }

    public function setTraceHandle($traceHandle)
    {
        $this->traceHandle = $traceHandle;
    }

    public function getDebuggerTraceTid()
    {
        return $this->debuggerTraceTid;
    }

    public function setDebuggerTraceTid($tid)
    {
        $this->debuggerTraceTid = $tid;
    }

    public function getTrace()
    {
        return $this->trace;
    }

    public function setTrace($trace)
    {
        $this->trace = $trace;
    }

    public function getDebuggerTrace()
    {
        return $this->debuggerTrace;
    }

    public function setDebuggerTrace($debuggerTrace)
    {
        $this->debuggerTrace = $debuggerTrace;
    }

    public function getHawk()
    {
        return $this->hawk;
    }

    public function setHawk($hawk)
    {
        $this->hawk = $hawk;
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function setArguments($arguments)
    {
        $this->arguments = $arguments;
    }
}