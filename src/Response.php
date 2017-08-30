<?php

namespace ZanPHP\Dubbo;


class Response
{
    const HEARTBEAT_EVENT = null;
    const READONLY_EVENT = "R";

    // byte
    const OK = 20;
    const CLIENT_TIMEOUT = 30;
    const SERVER_TIMEOUT = 31;
    const BAD_REQUEST = 40;
    const BAD_RESPONSE = 50;
    const SERVICE_NOT_FOUND = 60;
    const SERVICE_ERROR = 70;
    const SERVER_ERROR = 80;
    const CLIENT_ERROR = 90;


    private $id = 0;
    private $version;
    private $status = self::OK;
    private $isEvent = false;
    private $errorMsg;
    private $result;

    public function __construct($id, $version = null)
    {
        $this->id = $id;
        $this->version = $version;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function isEvent()
    {
        return $this->isEvent;
    }

    /**
     * @param string $isEvent
     */
    public function setEvent($isEvent)
    {
        $this->isEvent = true;
        $this->result = $isEvent;
    }

    public function isHeartbeat()
    {
        return $this->isEvent && self::HEARTBEAT_EVENT === $this->result;
    }

    public function setHeartbeat($isHeartbeat)
    {
        if ($isHeartbeat) {
            $this->setEvent(self::HEARTBEAT_EVENT);
        }
    }

    public function getErrorMessage()
    {
        return $this->errorMsg;
    }

    public function setErrorMessage($errorMsg)
    {
        $this->errorMsg = $errorMsg;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function setResult($result)
    {
        $this->result = $result;
    }

    public function __toString()
    {
        $result = $this->result === $this ? "this" : $this->result;
        return "Response [id=$this->id, version=$this->version, status=$this->status, event=$this->isEvent, error=$this->errorMsg, result=$result]";
    }
}