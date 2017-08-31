<?php

namespace ZanPHP\Dubbo;


use ZanPHP\Contracts\Codec\Codec;
use ZanPHP\Contracts\Codec\PDU;
use ZanPHP\Dubbo\Exception\DubboCodecException;

class DubboCodec implements Codec
{
    const NAME = "dubbo";
    const DUBBO_VERSION = "2.0.0";
    const HEADER_LENGTH = 16;

    // magic header. int16_t
    const MAGIC = 0xdabb;

    // message flag. int8_t
    const FLAG_REQUEST = 0x80;
    const FLAG_TWOWAY = 0x40;
    const FLAG_EVENT = 0x20;

    const SERIALIZATION_MASK = 0x1f;

    // int8_t
    const RESPONSE_WITH_EXCEPTION = 0;
    const RESPONSE_VALUE = 1;
    const RESPONSE_NULL_VALUE = 2;

    /**
     * @param PDU|Request|Response $msg
     * @return string
     * @throws DubboCodecException
     */
    public function encode(PDU $msg)
    {
        if ($msg instanceof Request) {
            return $this->encodeRequest($msg);
        } else if ($msg instanceof Response) {
            return $this->encodeResponse($msg);
        } else {
            throw new DubboCodecException();
        }
    }

    public function decode($bin)
    {
        $serialization = $this->getSerialization();

        $hdr = unpack('nmagic/Cflag/Cstatus/JreqId/NbodySz', $bin);
        $magic = $hdr["magic"]; // FIXME
        $flag = $hdr["flag"];
        $status = $hdr["status"];
        $reqId = $hdr["reqId"];
        $bodySize = $hdr["bodySz"]; // FIXME

        $in = $serialization->deserialize(substr($bin, self::HEADER_LENGTH));
        if (($flag & self::FLAG_REQUEST) == 0) {
            return $this->decodeResponse($in, $reqId, $flag, $status);
        } else {
            return $this->decodeRequest($in, $reqId, $flag);
        }
    }

    private function getSerialization($id = null)
    {
        $id = Hessian2Serialization::ID; // FIXME
        return CodecSupport::getSerializationById($id);
    }

    private function decodeRequest(Input $in, $id, $flag)
    {
        $req = new Request($id);
        $req->setVersion(self::DUBBO_VERSION);
        $req->setTwoWay(($flag & self::FLAG_TWOWAY) != 0);
        if (($flag & self::FLAG_EVENT) != 0) {
            $req->setEvent(Request::HEARTBEAT_EVENT);
        }
        try {
            if ($req->isHeartbeat() || $req->isEvent()) {
               $data = $this->decodeEvent($in);
            } else {
                $data = RpcInvocation::decode($in);
            }
            $req->setData($data);
            return $req;
        } catch (\Throwable $e) {
        } catch (\Exception $e) {}

        $req->setData($e);
        $req->setBroken(true);
        return $req;
    }

    private function decodeResponse(Input $in, $id, $flag, $status)
    {
        $res = new Response($id);
        if ($flag & self::FLAG_EVENT) {
            $res->setEvent(Response::HEARTBEAT_EVENT);
        }
        $res->setStatus($status);
        if ($status === Response::OK) {
            try {
                if ($res->isHeartbeat() || $res->isEvent()) {
                    $data = $this->decodeEvent($in);
                } else {
                    $data = RpcResult::decode($in);
                }
                $res->setResult($data);
                return $res;
            } catch (\Throwable $e) {
            } catch (\Exception $e) { }
            $res->setStatus(Response::CLIENT_ERROR);
            $exClass = gettype($e);
            $res->setErrorMessage("ERROR decoding dubbo response: ($exClass)" . $e->getMessage());
            return $res;
        } else {
            $res->setErrorMessage($in->readString());
            return $res;
        }
    }

    private function decodeEvent(Input $in)
    {
        try {
            return $in->readObject();
        } catch (\Throwable $t) {
            echo_exception($t);
        } catch (\Exception $e) {
            echo_exception($e);
        }
        return null;
    }

    private function encodeRequest(Request $req)
    {
        $serialization = $this->getSerialization();

        $serializationId = $serialization->getContentTypeId();
        $flag = self::FLAG_REQUEST | $serializationId;
        if ($req->isTwoWay()) {
            $flag |= self::FLAG_TWOWAY;
        }
        if ($req->isEvent()) {
            $flag |= static::FLAG_EVENT;
        }
        $status = 0;
        $reqId = $req->getId();

        $out = $serialization->serialize();
        if ($req->isEvent()) {
            $body = $out->write($req->getData());
        } else {
            $body = $this->encodeRequestData($out, $req->getData());
        }

        $bodySize = strlen($body);
        $header = pack('nCCJN', self::MAGIC, $flag, $status, $reqId, $bodySize);
        return $header . $body;
    }

    private function encodeResponse(Response $res)
    {
        $serialization = $this->getSerialization();

        $serializationId = $serialization->getContentTypeId();
        $flag = $serializationId;
        if ($res->isHeartbeat()) {
            $flag |= static::FLAG_EVENT;

        }
        $status = $res->getStatus();
        $reqId = $res->getId();

        $out = $serialization->serialize();
        // encode response data or error message.
        if ($status === Response::OK) {
            if ($res->isHeartbeat()) {
                $body = $out->write($res->getResult());
            } else {
                $body = $this->encodeResponseData($out, $res->getResult());
            }
        } else {
            $body = $out->writeString($res->getErrorMessage());
        }

        $bodySize = strlen($body);
        $header = pack('nCCJN', self::MAGIC, $flag, $status, $reqId, $bodySize);
        return $header . $body;
    }

    private function encodeRequestData(Output $out, RpcInvocation $inv)
    {
        $buf = "";
        $buf .= $out->writeString($inv->getVersion() ?: static::DUBBO_VERSION);
        $buf .= $out->writeString($inv->getServiceName());
        $buf .= $out->writeString($inv->getMethodVersion());
        $buf .= $out->writeString($inv->getMethodName());

        // FIXME ?!
//        if (substr(self::DUBBO_VERSION, 0, 3) === "2.8") {
//            $buf .= $out->write(-1);
//        }

        $buf .= $out->writeString(JavaType::types2desc($inv->getParameterTypes()));
        $args = $inv->getArguments();
        $paraTypes = $inv->getParameterTypes();
        foreach ($args as $i => $arg) {
            if ($arg instanceof JavaValue) {
                $arg = RpcInvocation::encodeInvocationArgument($inv, $paraTypes[$i], $arg);
                $buf .= $out->writeObject($arg, $paraTypes[$i]);
            } else {
                throw new \InvalidArgumentException();
            }
        }
        $buf .= $out->writeObject($inv->getAttachments());
        return $buf;
    }

    private function encodeResponseData(Output $out, Result $result)
    {
        $buf = "";
        if ($ex = $result->getException()) {
            $buf .= $out->writeByte(self::RESPONSE_WITH_EXCEPTION);
            $buf .= $out->writeObject($ex);
        } else {
            $ret = $result->getValue();
            if ($ret === null) {
                $buf .= $out->writeByte(self::RESPONSE_NULL_VALUE);
            } else {
                $buf .= $out->writeByte(self::RESPONSE_VALUE);
                $buf .= $out->writeObject($ret);
            }
        }
        return $buf;
    }
}