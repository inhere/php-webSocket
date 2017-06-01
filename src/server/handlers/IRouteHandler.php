<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2017/3/26 0026
 * Time: 15:35
 */

namespace inhere\webSocket\server\handlers;

use inhere\webSocket\server\Application;
use inhere\webSocket\http\Request;
use inhere\webSocket\http\Response;
use inhere\webSocket\parts\MessageBag;

/**
 * Interface IRouteHandler
 * @package inhere\webSocket\server\handlers
 */
interface IRouteHandler
{
    const SEND_PING = 'ping';
    const NOT_FOUND = 'notFound';
    const PARSE_ERROR = 'error';

    const DATA_JSON = 'json';
    const DATA_TEXT = 'text';

    /**
     * @param Request $request
     * @param Response $response
     */
    public function onHandshake(Request $request, Response $response);

    /**
     * @param int $id
     */
    public function onOpen(int $id);

    /**
     * @param int $id
     * @param array $client
     * @return
     */
    public function onClose(int $id, array $client);

    /**
     * @param Application $app
     * @param string $msg
     */
    public function onError(Application $app, string $msg);

    public function checkIsAllowedOrigin(string $from);

    /**
     * @param string $data
     * @param int $id
     * @return mixed
     */
    public function dispatch(string $data, int $id);

    /**
     * @param string $command
     * @param $handler
     * @return static
     */
    public function add(string $command, $handler);

    public function log(string $message, string $type = 'info', array $data = []);

    public function isJsonType();

    /**
     * @param $data
     * @param string $msg
     * @param int $code
     * @param bool $doSend
     * @return int|MessageBag
     */
    public function respond($data, string $msg = 'success', int $code = 0, bool $doSend = true);

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, $default = null);

    /**
     * @param Application $app
     */
    public function setApp(Application $app);

    /**
     * @return Application
     */
    public function getApp(): Application;

    /**
     * @return Request
     */
    public function getRequest(): Request;

    /**
     * @param Request $request
     */
    public function setRequest(Request $request);
}
