<?php
/**
 * @desc
 * @author
 * @version
 */

namespace ping\swoole;

use Ping;
use ping\base;

class Server extends base\Server
{
    private $_swoole = [
        'host'                     => '0.0.0.0',
        'port'                     => '9501',
        'mode'                     => SWOOLE_PROCESS,
        //work进程数据包,1 平均分配;2 取模分配，可使每个请求的数据分配到固定work进程，默认值; 3 抢占方式
        'dispatch_mode'            => 2,
        //设置为守护进程
        'daemonize'                => 1,
        //poll线程数量,默认值和cpu核数相同,利用多核特性
        //'reactor_num' => 4,
        //write线程数量，默认值和cpu核数相同,利用多核特性
        //'write_num' => 4,
        //worker进程数量，一般设置为cpu核数的1-4倍
        'worker_num'               => 4,
        //任务进程，必须设定onTask回调函数
        'task_worker_num'          => 2,
        //work 进程在处理完10000个请求后结束运行，防止内存溢出,0表示不自动重启，需要保持连接信息的设置为0
        'max_request'              => 10000,
        //TODO 搞清楚这个参数的真正的含义
        'timeout'                  => 10,
        /**
         * tcp 连接相关
         */
        //等待连接的请求排队最大数量
        'backlog'                  => 128,
        //最大可连接数
        'max_conn'                 => 100000,
        //tcp nodelay
        //'open_tcp_nodelay' => 1,
        //此参数设定一个秒数，当客户端连接连接到服务器时，在约定秒数内并不会触发accept，直到有数据发送，或者超时时才会触发。
        //'tcp_defer_accept'         => 5,
        /*
         * 心跳检测
         */
        //心跳检测间隔时间
        'heartbeat_check_interval' => 30,
        //tcp连接最大闲置时间，如果某连接(fd)发包时间距离限制超过30s,关闭此链接
        'heartbeat_idle_time'      => 30,
        /*
         * 通信包自动粘合
         */
        //打开buffer
        //'open_eof_check'           => true,
        // 设置EOF
        //'package_eof'              => "\r\n\r\n",
        'open_length_check'        => true,
        'package_length_type'      => 'N',
        'package_length_offset'    => 0,
        'package_body_offset'      => 4,
        'package_max_length'       => 102400,
        /*
         * 其他
         */
        //日志文件路径
        'log_file'                 => '/tmp/swoole/swoole.log',
        //启用cpu亲和设置
        'open_cpu_affinity'        => 1,
        //callback
        'callback'                 => [
            'class' => 'ping\\swoole\\Callback',
            //'keepalive' => 1,
        ],
    ];


    private static $_instance;

    public function __construct($config)
    {
        parent::__construct($config);
    }

    public function init()
    {
        parent::init();
        if(!extension_loaded('swoole'))
        {
            throw new \Exception("no swoole extension. get: https://github.com/matyhtf/swoole");
        }
        if(static::$_instance)
        {
            return true;
        }
        $setting = $this->getSwoole();
        static::$_instance = new \swoole_server($setting['host'], $setting['port'], $setting['mode']);
        //TODO unset host,port,mode
        static::$_instance->set($setting);
    }

    public function handleRequest()
    {
        $args = func_get_args();
        $data = $args[0];
        //TODO 简单实用 msgpack
        //FIXME 数据传输协议依赖swoole 底层组包功能，此处不在检查数据完整
        $datastr = substr($data, 4);
        $params = (object)msgpack_unpack($datastr);
        if(empty($params->op))
        {
            $params->op = 'main.main';
        }
        //TODO 这里的处理应该完善点
        list($ctrl, $method) = explode('.', $params->op);
        $className = $this->getCtrlNamespace() . '\\' . ucfirst($ctrl) . 'Ctrl';
        $ctrl = new $className();
        $ctrl->setParams($params);
        //发送msgpack编码数据，头部为数据长度
        $result = $ctrl->$method();
        if(Ping::$server->debug)
        {
            echo "swoole handle request:\n";
            echo "\tctrl: {$ctrl} method: {$method}\n";
            echo "\tparams:\n\t\t" . json_encode($params) . "\n";
            echo "\tresponse:\n\t\t" . json_encode($result) . "\n";
            echo "\n\n";
        }
        $rawResult = msgpack_pack($result);
        $rawResult = pack('N1', strlen($rawResult));
        return $rawResult;
    }

    public function run()
    {
        $callback = $this->get('callback');
        static::$_instance->on('Start', [$callback, 'onStart']);
        static::$_instance->on('Connect', [$callback, 'onConnect']);
        static::$_instance->on('Receive', [$callback, 'onReceive']);
        static::$_instance->on('Close', [$callback, 'onClose']);
        static::$_instance->on('Shutdown', [$callback, 'onShutdown']);
        $handlerArray = [
            'onTimer',
            'onWorkerStart',
            'onWorkerStop',
            'onMasterConnect',
            'onMasterClose',
            'onTask',
            'onFinish',
            'onWorkerError',
            'onManagerStart',
            'onManagerStop'
        ];
        foreach($handlerArray as $handler)
        {
            if(method_exists($callback, $handler))
            {
                static::$_instance->on(\str_replace('on', '', $handler), [$callback, $handler]);
            }
        }
        static::$_instance->start();

    }

    public function getSwoole()
    {
        return $this->_swoole;
    }

    public function setSwoole($swoole)
    {
        $this->_swoole = array_merge($this->_swoole, $swoole);
        if(!class_exists($this->_swoole['callback']['class']))
        {
            throw new \Exception("callback class not found:{$this->_swoole['callback']['class']}");
        }
        $this->set('callback', $this->_swoole['callback']);
        $callback = $this->get('callback');
        if(!is_subclass_of($callback, 'ping\\base\Callback'))
        {
            throw new \Exception("callback must be ping\\base\\Callback subclass");
        }
    }
}