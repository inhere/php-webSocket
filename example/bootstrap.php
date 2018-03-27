<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-08-17
 * Time: 18:23
 */

use Inhere\Library\DI\ContainerManager;
use Inhere\LibraryPlus\Log\FileLogger;
use Inhere\LibraryPlus\Log\ProcessLogger;
//use Inhere\Route\ORouter;

$di = ContainerManager::make();

$di->set('proLogger', [
    'target' => ProcessLogger::class,
    [
        'toConsole' => false,
    ]
]);

$di->set('logger', [
    'target' => FileLogger::class . '::make',
    [
        'name' => 'test',
        'logConsole' => false,
        'logThreshold' => 10,
    ]
]);

//$di->set('router', ORouter::make([
//    'ignoreLastSep' => true,
//    'tmpCacheNumber' => 100,
//]));

//$di->set('request', new Inhere\Http\Request());
//$di->set('response', new Inhere\Http\Response());

//var_dump($di['logger']);

return $di;
