<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-03-30
 * Time: 13:01
 */

namespace Inhere\WebSocket\Server;

use Inhere\Console\Utils\Show;
use MyLib\PhpUtil\PhpHelper;
use Inhere\Http\ServerRequest as Request;
use Inhere\Http\Response;
use MyLib\WebSocket\Util\WebSocketUtilTrait;
use Psr\Log\LogLevel;

/**
 * Class AServerDriver
 * @package Inhere\WebSocket\Server
 */
abstract class ServerAbstracter implements ServerInterface, LogInterface
{
    // use traits\ProcessLogTrait;
    // use traits\ProcessManageTrait;
    use WebSocketUtilTrait;

    /**
     * The statistics info for server/worker
     * @var array
     */
    protected $stat = [
        'start_time' => 0,
        'stop_time'  => 0,
        'start_times' => 0,
    ];

    /**
     * max connect client numbers of each worker
     * @var integer
     */
    protected $maxConnect = 200;

    /**
     * Workers will only live for 1 hour
     * @var integer
     */
    protected $maxLifetime = 3600;

    ////////////////////

    /**
     * the master socket
     * @var resource
     */
    protected $socket;

    /**
     * client total number
     * @var int
     */
    private $clientNumber = 0;


    /**
     * 连接的客户端列表
     * @var resource[]
     * [
     *  id => socket,
     * ]
     */
    protected $clients = [];

    /**
     * 连接的客户端信息列表
     * @var ClientMetadata[]
     * [
     *  cid => [ ip=> string , port => int, handshake => bool ], // bool: handshake status.
     * ]
     */
    protected $metas = [];

    /**
     * @var array
     */
    protected $config = [
        // if you setting name, will display on the process name.
        'name' => '',

        // server address HOST:PORT
        'server' => '',

        'conf_file' => '',

        // run in the background
        'daemon' => false,

        // will start 4 workers
        'worker_num' => 2,

        // Workers will only live for 1 hour, after will auto restart.
        'max_lifetime' => 3600,

        // now, max_lifetime is >= 3600 and <= 4200
        'restart_splay' => 600,

        // max handle 2000 request of each worker, after will auto restart.
        'max_request' => 2000,

        // max connect client numbers of each worker
        'max_connect' => 200,

        // the master process pid save file
        'pid_file' => 'ws_server.pid',

        // will record server stat data to file
        'stat_file' => 'stat.dat',

        // 连接超时时间 s
        'timeout' => 2.2,

        // log
        'log_level' => 4,
        // 'day' 'hour', if is empty, not split.
        'log_split' => 'day',
        // will write log by `syslog()`
        'log_syslog' => false,
        'log_file' => 'ws_server.log',

        // enable ssl
        'enable_ssl' => false,

        // 'buffer_size' => 8192, // 8kb

        // 设置写(发送)缓冲区 最大2m @see `StreamsServer::setBufferSize()`
        'write_buffer_size' => 2097152,

        // 设置读(接收)缓冲区 最大2m
        'read_buffer_size' => 2097152,

        // while 循环时间间隔 毫秒(ms) millisecond. 1s = 1000ms = 1000 000us
        'sleep_time' => 500,

        // 最大数据接收长度 1024 / 2048 byte
        'max_data_len' => 2048,

        // 数据块大小 byte 发送数据时将会按这个大小拆分发送
        'fragment_size' => 1024,
    ];

    /**
     * @return array
     */
    public function getSupportedEvents(): array
    {
        return [self::ON_CONNECT, self::ON_HANDSHAKE, self::ON_OPEN, self::ON_MESSAGE, self::ON_CLOSE, self::ON_ERROR];
    }

    /**
     * WebSocket constructor.
     * @param string $host
     * @param int $port
     * @param array $options
     */
    public function __construct(string $host = '0.0.0.0', int $port = 8080, array $options = [])
    {
        if (!PhpHelper::isCli()) {
            throw new \RuntimeException('The script must run in the CLI mode.');
        }

        if (!static::isSupported()) {
            // throw new \InvalidArgumentException("The extension [$this->name] is required for run the server.");
            $this->cliOut->error("Your system is not supported the driver: {$this->driver}, by " . static::class, -200);
        }

        $this->host = $host;
        $this->port = $port;

        parent::__construct($options);

        $this->cliOut->write("The webSocket server power by extension: <info>{$this->driver}</info>, driver class: <cyan>" . static::class . '</cyan>', 'info');
    }

    /**
     * showHelp
     * @param string $error
     */
    protected function showHelp($error = '')
    {
        if ($error) {
            $this->cliOut->error($error);
        }

        $vs = self::VERSION;
        $script = $this->cliIn->getScript();

        $this->cliOut->helpPanel([
            Show::HELP_DES => "WebSocket server, Version <comment>$vs</comment>",
            Show::HELP_USAGE => [
                "$script {COMMAND} [OPTIONS]",
                "$script -h|--help"
            ],
            Show::HELP_COMMANDS => [
                'start' => 'Start webSocket server(default)',
                'stop' => 'Stop running webSocket server',
                'restart' => 'Restart running webSocket server',
                'reload' => 'Reload all running workers of the server',
                'status' => 'Get server runtime status information',
            ],
            Show::HELP_OPTIONS => '  -c CONFIG          Load a custom worker manager configuration file
  -s HOST[:PORT]     Connect to server HOST and optional PORT, multi server separated by commas(\',\')

  -n NUMBER          Start NUMBER workers that do all jobs

  -l LOG_FILE        Log output to LOG_FILE or use keyword \'syslog\' for syslog support
  -p PID_FILE        File to write master process ID out to

  -r NUMBER          Maximum run job iterations per worker
  -x SECONDS         Maximum seconds for a worker to live
  -t SECONDS         Number of seconds server should wait for a worker to complete connection before timing out

  -v [LEVEL]         Increase verbosity level by one. eg: -v vv | -v vvv

  -d,--daemon        Daemon, detach and run in the background

  -h,--help          Shows this help information
  -V,--version       Display the version of the manager
  -D,--dump [all]    Parse the command line and config file then dump it to the screen and exit.',
        ]);
    }

    /**
     * show Version
     */
    protected function showVersion()
    {
        $this->cliOut->write(
            printf("WebSocket server tool. Version <info>%s</info>\n", self::VERSION),
            true,
            0
        );
    }

    /**
     * show Status
     * @param string $cmd
     * @param bool $watch
     */
    protected function showStatus($cmd = 'status', $watch = false)
    {
        $this->cliOut->warning('Un-completed ...' . $cmd . $watch, 0);
    }

    /**
     * dumpInfo
     * @param bool $allInfo
     */
    protected function dumpInfo($allInfo = false)
    {
        if ($allInfo) {
            $this->stdout("There are all information of the manager:\n" . PhpHelper::printVars($this));
        } else {
            $this->stdout("There are configure information:\n" . PhpHelper::printVars($this->config));
        }

        $this->quit();
    }

/////////////////////////////////////////////////////////////////////////////////////////
/// config parse methods
/////////////////////////////////////////////////////////////////////////////////////////

    /**
     * if you setting name, will display on the process name.
     * @var string
     */
    protected $name;

    /**
     * handle CLI command and load options
     * @return bool
     */
    protected function handleCommandAndConfig(): bool
    {
        $command = $this->cliIn->getCommand('start');
        $supported = ['start', 'stop', 'restart', 'reload', 'info', 'status'];

        if (!\in_array($command, $supported, true)) {
            $this->showHelp("The command [{$command}] is don't supported!");
        }

        $options = $this->cliIn->getOpts();

        // load CLI Options
        $this->loadCommandOptions($options);

        // init Config And Properties
        $this->initConfigAndProperties($this->config);

        // Debug option to dump the config and exit
        if (isset($options['D']) || isset($options['dump'])) {
            $val = $options['D'] ?? $options['dump'] ?? '';
            $this->dumpInfo($val === 'all');
        }

        $masterPid = ProcessHelper::getPidFromFile($this->pidFile);
        $isRunning = ProcessHelper::isRunning($masterPid);

        // start: do Start Server
        if ($command === 'start') {
            // check master process is running
            if ($isRunning) {
                $this->stderr("The worker manager has been running. (PID:{$masterPid})\n", true, -__LINE__);
            }

            return true;
        }

        // check master process
        if (!$isRunning) {
            $this->stderr("The worker manager is not running. can not execute the command: {$command}\n", true, -__LINE__);
        }

        // switch command
        switch ($command) {
            case 'stop':
            case 'restart':
                // stop: stop and exit. restart: stop and start
                $this->stopServer($masterPid, $command === 'stop');
                break;
            case 'reload':
                // reload workers
                $this->reloadWorkers($masterPid);
                break;
            case 'status':
                $cmd = $options['cmd'] ?? 'status';
                $this->showStatus($cmd, isset($options['watch-status']));
                break;
            default:
                $this->showHelp("The command [{$command}] is don't supported!");
                break;
        }

        return true;
    }

    /**
     * load the command line options
     * @param array $opts
     */
    protected function loadCommandOptions(array $opts)
    {
        $map = [
            'c' => 'conf_file', // config file
            's' => 'server',    // server address

            'n' => 'worker_num',  // worker number do all jobs
            'u' => 'user',
            'g' => 'group',

            'l' => 'log_file',
            'p' => 'pid_file',

            'r' => 'max_request', // max request for a worker
            'x' => 'max_lifetime',// max lifetime for a worker
            't' => 'timeout',
        ];

        // show help
        if (isset($opts['h']) || isset($opts['help'])) {
            $this->showHelp();
        }
        // show version
        if (isset($opts['V']) || isset($opts['version'])) {
            $this->showVersion();
        }

        // load opts values to config
        foreach ($map as $k => $v) {
            if (isset($opts[$k]) && $opts[$k]) {
                $this->config[$v] = $opts[$k];
            }
        }

        // load Custom Config File
        if ($file = $this->config['conf_file']) {
            if (!file_exists($file)) {
                $this->showHelp("Custom config file {$file} not found.");
            }

            $config = require $file;
            $this->setConfig($config);
        }

        // watch modify
//        if (isset($opts['w']) || isset($opts['watch'])) {
//            $this->config['watch_modify'] = $opts['w'];
//        }

        // run as daemon
        if (isset($opts['d']) || isset($opts['daemon'])) {
            $this->config['daemon'] = true;
        }

        if (isset($opts['v'])) {
            $opts['v'] = $opts['v'] === true ? '' : $opts['v'];

            switch ($opts['v']) {
                case '':
                    $this->config['log_level'] = self::LOG_INFO;
                    break;
                case 'v':
                    $this->config['log_level'] = self::LOG_PROC_INFO;
                    break;
                case 'vv':
                    $this->config['log_level'] = self::LOG_WORKER_INFO;
                    break;
                case 'vvv':
                    $this->config['log_level'] = self::LOG_DEBUG;
                    break;
                case 'vvvv':
                    $this->config['log_level'] = self::LOG_CRAZY;
                    break;
                default:
                    // $this->config['log_level'] = self::LOG_INFO;
                    break;
            }
        }
    }

    /**
     * @param array $config
     */
    protected function initConfigAndProperties(array $config)
    {
        // init config attributes

        $this->config['daemon'] = (bool)$config['daemon'];
        $this->config['pid_file'] = trim($config['pid_file']);
        $this->config['enable_ssl'] = (bool)$config['enable_ssl'];
        $this->config['worker_num'] = (int)$config['worker_num'];
        $this->config['log_level'] = (int)$config['log_level'];

        $logFile = trim($config['log_file']);

        if ($logFile === 'syslog') {
            $this->config['log_syslog'] = true;
            $this->config['log_file'] = '';
        } else {
            $this->config['log_file'] = $logFile;
        }

        $this->config['timeout'] = (int)$config['timeout'];
        $this->config['max_connect'] = (int)$config['max_connect'];
        $this->config['max_lifetime'] = (int)$config['max_lifetime'];
        $this->config['max_request'] = (int)$config['max_request'];
        $this->config['restart_splay'] = (int)$config['restart_splay'];
        $this->config['max_data_len'] = (int)$config['max_data_len'];

//        $this->config['watch_modify'] = (bool)$config['watch_modify'];
//        $this->config['watch_modify_interval'] = (int)$config['watch_modify_interval'];

        $this->config['write_buffer_size'] = (int)$config['write_buffer_size'];
        $this->config['read_buffer_size'] = (int)$config['read_buffer_size'];

        // config value fix ... ...

        if ($this->config['worker_num'] <= 0) {
            $this->config['worker_num'] = self::WORKER_NUM;
        }

        if ($this->config['max_lifetime'] < self::MIN_LIFETIME) {
            $this->config['max_lifetime'] = self::MAX_LIFETIME;
        }

        if ($this->config['max_request'] < self::MIN_REQUEST) {
            $this->config['max_request'] = self::MAX_REQUEST;
        }

        if ($this->config['restart_splay'] <= 100) {
            $this->config['restart_splay'] = self::RESTART_SPLAY;
        }

        if ($this->config['timeout'] <= self::MIN_TIMEOUT) {
            $this->config['timeout'] = self::TIMEOUT;
        }

//        if ($this->config['watch_modify_interval'] <= self::MIN_WATCH_INTERVAL) {
//            $this->config['watch_modify_interval'] = self::WATCH_INTERVAL;
//        }

        $setTime = (int)$config['sleep_time'];
        $this->config['sleep_time'] = $setTime >= 10 ? $setTime : 50;

        // init properties

        if ($server = trim($config['server'])) {
            if (strpos($server, ':')) {
                list($this->host, $this->port) = explode(':', $server, 2);
            } else {
                $this->host = $server;
            }
        }

        if (!$this->host) {
            $this->host = self::DEFAULT_HOST;
        }

        if (!$this->port || $this->port <= 0) {
            $this->port = self::DEFAULT_PORT;
        }

        $this->config['server'] = "{$this->host}:{$this->port}";

        $this->name = trim($config['name']);
        $this->maxLifetime = $this->config['max_lifetime'];
        $this->logLevel = $this->config['log_level'];
        $this->pidFile = $this->config['pid_file'];
    }

/////////////////////////////////////////////////////////////////////////////////////////
/// start methods
/////////////////////////////////////////////////////////////////////////////////////////

    protected function beforeStart()
    {}

    /**
     * start server
     */
    public function start()
    {
        $this->beforeStart();

        $max = $this->config['max_connect'];
        $this->log("Started WebSocket server on <info>{$this->getHost()}:{$this->getPort()}</info> (max allow connection: $max)", 'info');

        // prepare something for start
        $this->prepare();

        $this->doStart();

        $this->afterStart();
    }

    protected function beforeStartWorkers()
    {}

    protected function afterStart()
    {
        $this->log('Stopping Manager ...', self::LOG_PROC_INFO);

        $this->quit();
    }

    /**
     * create and prepare socket resource
     */
    abstract protected function prepare();

    /**
     * do start server
     */
    abstract protected function doStart();

    /////////////////////////////////////////////////////////////////////////////////////////
    /// process manage methods
    /////////////////////////////////////////////////////////////////////////////////////////

    public function reset()
    {
        $this->metas = $this->clients = [];
    }

    public function __destruct()
    {
        $this->reset();
    }

    /**
     * @return bool
     */
    public function shouldContinue(): bool
    {
        return true;
    }

    /////////////////////////////////////////////////////////////////////////////////////////
    /// handle ws events method
    /////////////////////////////////////////////////////////////////////////////////////////

    /**
     * 增加一个初次连接的客户端 同时记录到握手列表，标记为未握手
     * @param resource|int $socket
     */
    protected function connect($socket)
    {
        $cid = $this->resourceId($socket);
        $data = $this->getPeerName($socket);
        $meta = [
            'ip' => $data['ip'],
            'port' => $data['port'],
            'handshake' => false,
            'path' => '/',
            'connectTime' => time(),
            'resourceId' => $cid,
        ];

        // 初始化客户端信息
        $this->metas[$cid] = new ClientMetadata($meta);
        // 客户端连接单独保存
        $this->clients[$cid] = $socket;
        $this->clientNumber++;

        $this->log("Connect: A new client connected, ID: $cid, From {$meta['ip']}:{$meta['port']}. Count: {$this->clientNumber}");

        // 触发 connect 事件回调
        $this->fire(self::ON_CONNECT, [$this, $cid]);
    }

    protected function resourceId($resource): int
    {
        return (int)$resource;
    }

    /**
     * 响应升级协议(握手)
     * Response to upgrade agreement (handshake)
     * @param resource $socket
     * @param string $data
     * @param int $cid
     * @return bool|mixed
     */
    protected function handshake($socket, string $data, int $cid)
    {
        $this->log("Handshake: Ready to shake hands with the #$cid client connection. request:\n$data");
        $meta = $this->metas[$cid];
        $response = new Response();
        // 解析请求头信息
        $request = Request::makeByParseRawData($data);
        $secKey = $request->getHeader('Sec-WebSocket-Key');

        // 解析请求头信息错误
        if ($this->isInvalidSecWSKey($secKey)) {
            $this->log("handle handshake failed! [Sec-WebSocket-Key] not found in header. Data: \n $data", 'error');

            $response
                ->setStatus(404)
                ->setBody('<b>400 Bad Request</b><br>[Sec-WebSocket-Key] not found in request header.');

            $this->writeTo($socket, $response->toString());

            return $this->close($cid, $socket, false);
        }

        // 触发 handshake 事件回调，如果返回 false -- 拒绝连接，比如需要认证，限定路由，限定ip，限定domain等
        // 就停止继续处理。并返回信息给客户端
        if (false === $this->fire(self::ON_HANDSHAKE, [$request, $response, $cid])) {
            $this->log("The #$cid client handshake's callback return false, will close the connection", 'notice');
            $this->writeTo($socket, $response->toString());

            return $this->close($cid, $socket, false);
        }

        /**
         * @TODO
         *   ? Origin;
         *   ? Sec-WebSocket-Protocol;
         *   ? Sec-WebSocket-Extensions.
         */

        // setting response
        $response
            ->setStatus(101)
            ->setHeaders([
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Accept' => $this->genSign($secKey),
                'Sec-WebSocket-Version' => self::WS_VERSION,
            ]);

        // 响应握手成功
        $respData = $response->toString();
        $this->debug("Handshake: response info:\n" . $respData);
        $this->writeTo($socket, $respData);

        // 标记已经握手 更新路由 path
        $meta->handshake();
        $meta->setPath($request->getPath());
        // $this->metas[$cid] = $meta;

        $this->log("The #$cid client connection handshake successful! Info:", 'info', $meta->all());

        // 握手成功 触发 open 事件
        return $this->fire(self::ON_OPEN, [$this, $request, $cid]);
    }

    /**
     * handle client message
     * @param int $cid
     * @param string $data
     * @param int $bytes
     * @param ClientMetadata $meta The client info
     */
    protected function message(int $cid, string $data, int $bytes, ClientMetadata $meta = null)
    {
        $meta = $meta ?: $this->getMeta($cid);
        $data = $this->decode($data);

        $this->log("Message: Received $bytes bytes message from #$cid, Data: $data");

        // call on message handler
        $this->fire(self::ON_MESSAGE, [$this, $data, $cid, $meta]);
    }

    /**
     * disconnect a connection
     * @param int $cid
     * @param null|resource $socket
     * @param bool $triggerEvent
     * @return bool
     */
    public function close(int $cid, $socket = null, bool $triggerEvent = true): bool
    {
        $this->log("Close: Will close the #$cid client connection");

        $ret = $this->doClose($cid, $socket);

        $this->afterClose($cid, $triggerEvent);

        return $ret;
    }

    /**
     * Closing a connection
     * @param int $cid
     * @param resource|null $socket
     * @return bool
     */
    abstract protected function doClose(int $cid, $socket = null): bool;

    /**
     * @param int $cid
     * @param bool $triggerEvent
     */
    protected function afterClose(int $cid, bool $triggerEvent = true)
    {
        $meta = $this->metas[$cid];
        $this->clientNumber--;

        unset($this->metas[$cid], $this->clients[$cid]);

        // call on close callback
        if ($triggerEvent) {
            $this->fire(self::ON_CLOSE, [$this, $cid, $meta]);
        }

        $this->log("Close: The #$cid client connection has been closed! From {$meta['ip']}:{$meta['port']}. Count: {$this->clientNumber}");
    }

    /**
     * @param $msg
     */
    protected function error($msg)
    {
        $this->log("An error occurred! Error: $msg", 'error');

        $this->fire(self::ON_ERROR, [$msg, $this]);
    }

    /////////////////////////////////////////////////////////////////////////////////////////
    /// send message to client
    /////////////////////////////////////////////////////////////////////////////////////////

    /**
     * send message
     * @param string $data
     * @param int $sender
     * @param int|array|null $receiver
     * @param int[] $expected
     * @return int
     */
    public function send(string $data, int $sender = 0, $receiver = null, array $expected = []): int
    {
        // only one receiver
        if ($receiver && (($isInt = \is_int($receiver)) || 1 === \count($receiver))) {
            $receiver = $isInt ? $receiver : array_shift($receiver);

            return $this->sendTo($receiver, $data, $sender);
        }

        return $this->broadcast($data, (array)$receiver, $expected, $sender);
    }

    /**
     * Send a message to the specified user 发送消息给指定的用户
     * @param int $receiver 接收者
     * @param string $data
     * @param int $sender 发送者
     * @return int
     */
    public function sendTo(int $receiver, string $data, int $sender = 0): int
    {
        if (!$data || $receiver < 1) {
            return 0;
        }

        if (!($socket = $this->getClient($receiver))) {
            $this->log("The target user #$receiver not connected or has been logout!", 'error');

            return 0;
        }

        $res = $this->frame($data);
        $fromUser = $sender < 1 ? 'SYSTEM' : $sender;

        $this->log("(private)The #{$fromUser} send message to the user #{$receiver}. Data: {$data}");

        return $this->writeTo($socket, $res);
    }

    /**
     * broadcast message 广播消息
     * @param string $data 消息数据
     * @param int $sender 发送者
     * @param int[] $receivers 指定接收者们
     * @param int[] $expected 要排除的接收者
     * @return int   Return socket last error number code.  gt 0 on failure, eq 0 on success
     */
    public function broadcast(string $data, array $receivers = [], array $expected = [], int $sender = 0): int
    {
        if (!$data) {
            return 0;
        }

        // only one receiver
        if (1 === \count($receivers)) {
            return $this->sendTo(array_shift($receivers), $data, $sender);
        }

        $res = $this->frame($data);
        $len = \strlen($res);
        $fromUser = $sender < 1 ? 'SYSTEM' : $sender;

        // to all
        if (!$expected && !$receivers) {
            $this->log("(broadcast)The #{$fromUser} send a message to all users. Data: {$data}");

            foreach ($this->clients as $socket) {
                $this->writeTo($socket, $res, $len);
            }

            // to receivers
        } elseif ($receivers) {
            $this->log("(broadcast)The #{$fromUser} gave some specified user sending a message. Data: {$data}");
            foreach ($receivers as $receiver) {
                if ($socket = $this->getClient($receiver)) {
                    $this->writeTo($socket, $res, $len);
                }
            }

            // to special users
        } else {
            $this->log("(broadcast)The #{$fromUser} send the message to everyone except some people. Data: {$data}");
            foreach ($this->clients as $cid => $socket) {
                if (isset($expected[$cid])) {
                    continue;
                }

                if ($receivers && !isset($receivers[$cid])) {
                    continue;
                }

                $this->writeTo($socket, $res, $len);
            }
        }

        return $this->getErrorNo();
    }

    /**
     * response data to client by socket connection
     * @param resource $socket
     * @param string $data
     * @param int $length
     * @return int      Return socket last error number code. gt 0 on failure, eq 0 on success
     */
    abstract public function writeTo($socket, string $data, int $length = 0): int;

    /**
     * @param null|resource $socket
     * @return int
     */
    abstract public function getErrorNo($socket = null): int ;

    /**
     * @param null|resource $socket
     * @return string
     */
    abstract public function getErrorMsg($socket = null): string ;

    /**
     * 获取对端socket的IP地址和端口
     * @param resource|int $socket Driver is sockets or streams, type is resource. Driver is swoole type is int.
     * @return array
     */
    abstract public function getPeerName($socket): array ;

    /////////////////////////////////////////////////////////////////////////////////////////
    /// helper method
    /////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Logs data to disk or stdout
     * @param string $msg
     * @param int|string $level
     * @param array $data
     */
    public function log(string $msg, $level = LogLevel::INFO, array $data = [])
    {
        if ($this->isDebug() && ($info = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1))) {
            $info = $info[0];
            $msg = sprintf('[%s:%d] ', $info['class'] ?? 'UNKNOWN', $info['line'] ?? -1) . $msg;
        }

        // if not in daemon, print log to \STDOUT
//        if (!$this->isDaemon()) {
//            list($ts, $ms) = explode('.', sprintf('%.4f', microtime(true)));
//            $ds = date('Y/m/d H:i:s', $ts) . '.' . $ms;
//
//            $logString = sprintf(
//                '[%s] [%s] %s %s' . PHP_EOL,
//                $ds, $this->logger::getLevelName($level), trim($msg), json_encode($data)
//            );
//
//            $this->stdout($logString, false);
//        }

        $msg = $this->getCliOut()->getStyle()->format($msg);

        $this->logger->log($msg, $level, $data);
    }

    /**
     * @param string $msg
     * @param array $data
     */
    public function debug(string $msg, array $data = [])
    {
        $this->log($msg, ProcessLogInterface::DEBUG, $data);
    }

    /**
     * @param string $secWSKey 'sec-websocket-key: xxxx'
     * @return bool
     */
    public function isInvalidSecWSKey($secWSKey): bool
    {
        return 0 === preg_match(self::WS_KEY_PATTEN, $secWSKey) || 16 !== \strlen(base64_decode($secWSKey));
    }

    /**
     * packData encode
     * @param string $s
     * @return string
     */
    public function frame(string $s): string
    {
        /** @var array $a */
        $a = str_split($s, 125);
        $prefix = self::BINARY_TYPE_BLOB;

        // <= 125
        if (\count($a) === 1) {
            return $prefix . \chr(\strlen($a[0])) . $a[0];
        }

        $ns = '';

        foreach ($a as $o) {
            $ns .= $prefix . \chr(\strlen($o)) . $o;
        }

        return $ns;
    }

    /**
     * @param $buffer
     * @return string
     */
    public function decode($buffer)
    {
        /*$len = $masks = $data =*/
        $decoded = '';

        $fin = (\ord($buffer{0}) & 0x80) === 0x80; // 1bit，1表示最后一帧
        if (!$fin) {
            return '';// 超过一帧暂不处理
        }

        $maskFlag = (\ord($buffer{1}) & 0x80) === 0x80; // 是否包含掩码 0x80 -> 128
        if (!$maskFlag) {
            return '';// 不包含掩码的暂不处理
        }

        // $len = ord($buffer[1]) & 0x7F; // 数据长度
        $len = \ord($buffer[1]) & 127; // 数据长度 0x7F -> 127

        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }

        $dataLen = \strlen($data);

        for ($index = 0; $index < $dataLen; $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }

        return $decoded;
    }

    /////////////////////////////////////////////////////////////////////////////////////////
    /// getter/setter method
    /////////////////////////////////////////////////////////////////////////////////////////

    /**
     *  check it is a accepted client
     * @notice maybe don't complete handshake
     * @param $cid
     * @return bool
     */
    public function hasMeta(int $cid): bool
    {
        return isset($this->metas[$cid]);
    }

    /**
     * get client info data
     * @param int $cid
     * @return ClientMetadata
     */
    public function getMeta(int $cid): ClientMetadata
    {
        return $this->metas[$cid] ?? null;
    }

    /**
     * get client info data
     * @param int $cid
     * @return ClientMetadata
     */
    public function getClientInfo(int $cid)
    {
        return $this->getMeta($cid);
    }

    /**
     * @return array
     */
    public function getMetas(): array
    {
        return $this->metas;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->clientNumber;
    }

    /**
     * check it a accepted client and handshake completed  client
     * @param int $cid
     * @return bool
     */
    public function isHandshake(int $cid): bool
    {
        if ($this->hasMeta($cid)) {
            return $this->getMeta($cid)['handshake'];
        }

        return false;
    }

    /**
     * count handshake clients
     * @return int
     */
    public function countHandshake(): int
    {
        $count = 0;

        foreach ($this->metas as $info) {
            if ($info['handshake']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     *  check it is a exists client
     * @notice maybe don't complete handshake
     * @param $cid
     * @return bool
     */
    public function hasClient(int $cid): bool
    {
        return isset($this->clients[$cid]);
    }

    /**
     * check it is a accepted client socket
     * @notice maybe don't complete handshake
     * @param  resource $socket
     * @return bool
     */
    public function isClient($socket): bool
    {
        return \in_array($socket, $this->clients, true);
    }

    /**
     * get client socket connection by index
     * @param $cid
     * @return resource|false
     */
    public function getClient($cid)
    {
        if ($this->hasMeta($cid)) {
            return $this->clients[$cid];
        }

        return false;
    }

    /**
     * @return array
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * @return resource
     */
    public function getSocket(): resource
    {
        return $this->socket;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getShowName(): string
    {
        return $this->name ? "({$this->name})" : '';
    }

    /**
     * @return bool
     */
    public function isDaemon(): bool
    {
        return (bool)$this->get('daemon', false);
    }
}
