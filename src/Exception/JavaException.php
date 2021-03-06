<?php

namespace ZanPHP\Dubbo\Exception;


class JavaException extends RpcException
{
    protected $bt = [];
    protected $class;
    protected $msg;

    public function __construct(\stdClass $obj)
    {
        $this->class = isset($obj->__type) ? $obj->__type : "";
        $this->msg = isset($obj->detailMessage) ? $obj->detailMessage : "";
        $trace = isset($obj->stackTrace) ? $obj->stackTrace : [];
        $this->bt = $this->parserStackTrace($trace);

        parent::__construct("[$this->class]$this->msg", 0, null, $this->bt);
    }

    private function parserStackTrace(array $bt)
    {
        $nbt = [];
        foreach ($bt as $frame) {
            if (!($frame instanceof \stdClass)) {
                continue;
            }
            $a = [];
            $a["declaringClass"] = isset($frame->declaringClass) ? $frame->declaringClass : "";
            $a["methodName"] = isset($frame->methodName) ? $frame->fileName : "";
            $a["fileName"] = isset($frame->fileName) ? $frame->fileName : "";
            $a["lineNumber"] = isset($frame->lineNumber) ? $frame->lineNumber : -1;
            $nbt[] = $a;
        }
        return $nbt;
    }

    public function __toString()
    {
        $s = "";
        $i = 0;
        foreach (array_reverse($this->bt) as $frame) {
            $s .= "#[$i] {$frame["declaringClass"]}.{$frame["methodName"]} at [{$frame["fileName"]}:{$frame["lineNumber"]}]\n";
            $i++;
        }

        return "Class: $this->class\nMessage: $this->msg\n\n" . parent::__toString();
    }
}