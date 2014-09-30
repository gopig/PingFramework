<?php
/**
 * @desc
 * @author
 * @version
 */

namespace ping\swoole;

use Ping;
use ping\base;

class Callback extends base\Callback
{
    public static $keepalive = true;
    public static $workServer;

    /**
     * 当socket服务启动时，回调此方法
     */
    public static function onStart()
    {
        echo 'swoole start, swoole version: ' . SWOOLE_VERSION . PHP_EOL;
        //FIXME 完善opcode  清理功能
        if(extension_loaded('Zend OPcache'))
        {
            opcache_reset();
        }
    }

    /**
     * 当有client连接上socket服务时，回调此方法
     */
    public static function onConnect()
    {
        $params = func_get_args();
        $fd = $params[1];
        echo "{$fd} connected" . PHP_EOL;
        //$swoole = $params[0];
        //$swoole->task('test');
    }

    /**
     * 当有数据到达时，回调此方法
     */
    public static function onReceive()
    {
        $params = func_get_args();
        $fd = $params[1];
        $data = $params[3];
        //调用路由器处理数据 返回结果
        $response = Ping::$server->handleRequest($data);
        static::onSend($fd, $response);
    }

    public static function onSend($fd, $response)
    {
        if(!static::$workServer->send($fd, $response))
        {
            echo "send data to client :{$fd} failed\n";
        }
        else
        {
            //echo "send data:{$result}\n\n";
        }

        if(!static::$keepalive)
        {
            static::$workServer->close($fd);
        }
    }

    /**
     * 当有client断开时，回调此方法
     */
    public static function onClose()
    {
        $params = func_get_args();
        $fd = $params[1];
        echo "{$fd} closed" . PHP_EOL;
        //$this->_cache->delBuff($params[1]);
    }

    /**
     * 当socket服务关闭时，回调此方法
     */
    public static function onShutdown()
    {
        echo "swoole shut dowm\n";
    }

    public static function onWorkerStart()
    {
        $params = func_get_args();
        static::$workServer = $params[0];
        //$worker_id = $params[1];
        //echo "WorkerStart[$worker_id]|pid=" . posix_getpid() . ".\n";
    }

    public static function onWorkerStop()
    {
        /*
        $params = func_get_args();
        $worker_id = $params[1];
        echo "WorkerStop[$worker_id]|pid=" . posix_getpid() . ".\n";
        */
    }

    public static function onTask()
    {
        $params = func_get_args();
        $task_id = $params[1];
        $data = $params[3];
        echo "New AsyncTask[id=$task_id], the data is {$data}" . PHP_EOL;
    }

    public static function onFinish()
    {

    }

} 