<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2017/3/29 0029
 * Time: 21:32
 */

namespace Inhere\WebSocket;

use MyLib\ObjUtil\Traits\ArrayAccessByPropertyTrait;
use MyLib\ObjUtil\Traits\PropertyAccessByGetterSetterTrait;
use Inhere\WebSocket\Server\ServerInterface;

/**
 * Class WSResponse
 * webSocket message response
 * @package Inhere\WebSocket\Part
 */
class Message implements \ArrayAccess
{
    use ArrayAccessByPropertyTrait;
    use PropertyAccessByGetterSetterTrait;

    /**
     * @var ServerInterface
     */
    private $ws;

    /**
     * the sender id
     * @var int
     */
    private $sender;

    /**
     * the receivers id list
     * @var array
     */
    private $receivers;

    /**
     * the excepted id list
     * @var array
     */
    private $excepted;

    /**
     * @var string
     */
    private $data;

    public static function make(string $data = '', int $sender = 0, array $receivers = [], array $excepted = [])
    {
        return new self($data, $sender, $receivers, $excepted);
    }

    /**
     * MessageResponse constructor.
     * @param string $data
     * @param int $sender
     * @param array $receivers
     * @param array $excepted
     */
    public function __construct(string $data = '', int $sender = 0, array $receivers = [], array $excepted = [])
    {
        $this->data = $data;
        $this->sender = $sender;
        $this->receivers = $receivers;
        $this->excepted = $excepted;
    }

    /**
     * mark message sent
     * @var bool
     */
    private $_sent = false;

    /**
     * last status code
     * @var int
     */
    private $_status = 0;

    /**
     * @param bool $reset
     * @return int
     */
    public function send(bool $reset = true): int
    {
        if (!$this->ws) {
            throw new \InvalidArgumentException('Please set the property [ws], is instance of the ServerInterface');
        }

        // if message have been sent, stop and return last status code
        if ($this->isSent()) {
            return $this->_status;
        }

        $status = $this->ws->send($this->getData(), $this->sender, $this->receivers, $this->excepted);

        if ($reset) {
            $this->reset();
        }

        // mark message have been sent
        $this->_sent = true;
        $this->_status = $status;

        return $status;
    }

    /**
     * reset
     */
    public function reset()
    {
        $this->_sent = false;
        $this->sender = $this->_status = 0;
        $this->receivers = $this->excepted = $this->data = [];
    }

    public function __destruct()
    {
        $this->ws = null;
        $this->reset();
    }

    /**
     * @return bool
     */
    public function isSent(): bool
    {
        return $this->_sent;
    }

    /**
     * @param bool $sent
     */
    public function setSent(bool $sent)
    {
        $this->_sent = $sent;
    }

    /**
     * @return int
     */
    public function getSender(): int
    {
        return $this->sender;
    }

    /**
     * @param int $sender
     * @return $this
     */
    public function sender(int $sender): self
    {
        return $this->setSender($sender);
    }

    public function from(int $sender)
    {
        return $this->setSender($sender);
    }

    public function setSender(int $sender)
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * @return array
     */
    public function getReceivers(): array
    {
        return $this->receivers;
    }

    /**
     * @param int $cid
     * @return $this
     */
    public function receiver(int $cid): self
    {
        return $this->addReceiver($cid);
    }

    public function addReceiver(int $cid)
    {
        if (!\in_array($cid, $this->receivers, true)) {
            $this->receivers[] = $cid;
        }

        return $this;
    }

    /**
     * @param array|int $receivers
     * @return $this
     */
    public function to($receivers): self
    {
        return $this->setReceivers($receivers);
    }

    public function setReceivers($receivers)
    {
        $this->receivers = (array)$receivers;

        return $this;
    }

    /**
     * @return array
     */
    public function getExcepted(): array
    {
        return $this->excepted;
    }

    /**
     * @param $receiver
     * @return $this
     */
    public function except(int $receiver): self
    {
        if (!\in_array($receiver, $this->excepted, true)) {
            $this->excepted[] = $receiver;
        }

        return $this;
    }

    /**
     * @param array|int $excepted
     * @return $this
     */
    public function setExcepted($excepted): self
    {
        $this->excepted = (array)$excepted;

        return $this;
    }

    /**
     * @param string $data
     * @param bool $toLast
     * @return $this
     */
    public function addData(string $data, bool $toLast = true): self
    {
        if ($toLast) {
            $this->data .= $data;
        } else {
            $this->data = $data . $this->data;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @param string $data
     * @return self
     */
    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return ServerInterface
     */
    public function getWs(): ServerInterface
    {
        return $this->ws;
    }

    /**
     * @param ServerInterface $ws
     * @return self
     */
    public function setWs(ServerInterface $ws): self
    {
        if (!$this->ws) {
            $this->ws = $ws;
        }

        return $this;
    }
}
