<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-01
 * Time: 12:05
 */

namespace inhere\webSocket\server\drivers;

/**
 * Class StreamDriver
 * @package inhere\webSocket\server\drivers
 */
class StreamDriver implements IServerDriver
{
    /**
     * @inheritdoc
     */
    public static function isSupported()
    {
        return function_exists('stream_socket_accept');
    }

    public function start()
    {
        $this->socket = stream_socket_server(
            $this->getUri(),
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $this->getStreamContext()
        );
        if (!$this->socket) {
            throw new ConnectionException(sprintf(
                'Could not listen on socket: %s (%d)',
                $errstr,
                $errno
            ));
        }
        $this->listening = true;
    }
}