<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Contracts\Codec\PDU;

class Request implements PDU
{
    const HEARTBEAT_EVENT = null;
    const READONLY_EVENT = "R";

    private $id;
    private $version = DubboCodec::DUBBO_VERSION;
    private $isTwoWay = true;
    private $isEvent = false;
    private $isBroken = false;
    private $data;

    public function __construct($id = null)
    {
        if ($id === null) {
            $this->id = self::newId();
        }
        $this->id = $id;
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

    public function isTwoWay()
    {
        return $this->isTwoWay;
    }

    public function setTwoWay($isTwoWay)
    {
        $this->isTwoWay = boolval($isTwoWay);
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
        $this->data = $isEvent;
    }

    public function isBroken()
    {
        return $this->isBroken;
    }

    public function setBroken($isBroken)
    {
        $this->isBroken = boolval($isBroken);
    }

    /**
     * @return RpcInvocation|mixed $msg
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param RpcInvocation|mixed $msg
     */
    public function setData($msg)
    {
        $this->data = $msg;
    }

    public function isHeartbeat()
    {
        return $this->isEvent && self::HEARTBEAT_EVENT === $this->data;
    }

    public function setHeartbeat($isHeartbeat)
    {
        if ($isHeartbeat) {
            $this->setEvent(self::HEARTBEAT_EVENT);
        }
    }

    public function __toString()
    {
        $data = $this->data === $this ? "this" : $this->data;
        return "Request [id=$this->id, version=$this->version, twoway=$this->isTwoWay, event=$this->isEvent, broken=$this->isBroken, data=$data]";
    }

    public static function newId()
    {
        static $seq = 0;

        if (++$seq === PHP_INT_MAX) {
            $seq = 1;
        }
        return $seq;
    }
}