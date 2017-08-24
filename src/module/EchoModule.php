<?php
/**
 * Created by PhpStorm.
 * User: Inhere
 * Date: 2017/3/26 0026
 * Time: 15:34
 */

namespace inhere\webSocket\module;

use inhere\webSocket\http\Request;
use inhere\webSocket\http\Response;

/**
 * Class EchoModule
 *
 * handle the root '/echo' webSocket request
 *
 * @package inhere\webSocket\module
 */
class EchoModule extends ModuleAbstracter
{
    /**
     * @param Request $request
     * @param Response $response
     */
    public function onHandshake(Request $request, Response $response)
    {
        parent::onHandshake($request, $response);

        $response->setCookie('test', 'test-value');
        $response->setCookie('test1', 'test-value1');
    }

    /**
     * index command
     * the default command
     */
    public function indexCommand()
    {
        $this->respond('hello, welcome to here!');
    }
}