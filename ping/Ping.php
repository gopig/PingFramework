<?php
/**
 * This is a simple framework with swoole
 * Framework name is ping , it is my lover's name, or you can think it is the commad `ping`
 *
 * @link      tobe continue
 * @copyright lee.wanghui@gmail.com
 * @license
 */

/**
 * TODO
 * - logger 实现现在不行
 * - 错误异常处理不行
 * - 性能不行
 * - 逻辑严谨性
 */
use ping\log\Logger;

defined('PING_BEGIN_TIME') or define('PING_BEGIN_TIME', microtime(true));
defined('PING_PATH') or define('PING_PATH', __DIR__);

class Ping
{
    /**
     * 日志工具
     *
     * @var ping\log\Logger
     */
    private static $_logger;
    /**
     * 应用运行模式
     *
     * @var ping\base\Server|ping\swoole\Server
     */
    public static $server;
    /**
     * 依赖注入容器
     *
     * @var ping\di\Container
     */
    public static $container;
    /**
     * 预加载类
     *
     * @var array
     */
    public static $classMap = [];

    /**
     * psr4 自动加载实现别名
     *
     * @var array
     */
    public static $aliases = [
        '@ping' => __DIR__,
    ];

    /**
     * 自动加载函数, 在psr4中不建议抛出异常,框架就注册一个autoloader，故抛出异常更好检测错误
     *
     * @param $className
     *
     * @throws Exception
     */
    public static function autoload($className)
    {
        if(isset(static::$classMap[$className]))
        {
            $classFile = static::$classMap[$className];
            if($classFile[0] === '@')
            {
                $classFile = static::getAlias($classFile);
            }
        }
        elseif(strpos($className, '\\') !== false)
        {
            $classFile = static::getAlias('@' . str_replace('\\', '/', $className) . '.php', false);
            if($classFile === false || !is_file($classFile))
            {
                return;
            }
        }
        else
        {
            return;
        }
        include($classFile);

        if(!class_exists($className, false) && !interface_exists($className, false) && !trait_exists($className, false))
        {
            throw new \Exception("Unable to find '$className' in file: $classFile. Namespace missing?");
        }
    }

    /**
     * 获取路径别名
     *
     * @param      $alias
     * @param bool $throwException
     *
     * @return bool|string
     * @throws Exception
     */
    public static function getAlias($alias, $throwException = true)
    {
        if(strncmp($alias, '@', 1))
        {
            return $alias;
        }

        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        if(isset(static::$aliases[$root]))
        {
            if(is_string(static::$aliases[$root]))
            {
                return $pos === false ? static::$aliases[$root] : static::$aliases[$root] . substr($alias, $pos);
            }
            else
            {
                foreach(static::$aliases[$root] as $name => $path)
                {
                    if(strpos($alias . '/', $name . '/') === 0)
                    {
                        return $path . substr($alias, strlen($name));
                    }
                }
            }
        }

        if($throwException)
        {
            throw new \Exception("Invalid path alias: $alias");
        }
        else
        {
            return false;
        }
    }

    /**
     * 设置路径别名
     *
     * @param $alias
     * @param $path
     *
     * @throws Exception
     */
    public static function setAlias($alias, $path)
    {
        if(strncmp($alias, '@', 1))
        {
            $alias = '@' . $alias;
        }
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);
        if($path !== null)
        {
            $path = strncmp($path, '@', 1) ? rtrim($path, '\\/') : static::getAlias($path);
            if(!isset(static::$aliases[$root]))
            {
                if($pos === false)
                {
                    static::$aliases[$root] = $path;
                }
                else
                {
                    static::$aliases[$root] = [$alias => $path];
                }
            }
            elseif(is_string(static::$aliases[$root]))
            {
                if($pos === false)
                {
                    static::$aliases[$root] = $path;
                }
                else
                {
                    static::$aliases[$root] = [
                        $alias => $path,
                        $root  => static::$aliases[$root],
                    ];
                }
            }
            else
            {
                static::$aliases[$root][$alias] = $path;
                krsort(static::$aliases[$root]);
            }
        }
        elseif(isset(static::$aliases[$root]))
        {
            if(is_array(static::$aliases[$root]))
            {
                unset(static::$aliases[$root][$alias]);
            }
            elseif($pos === false)
            {
                unset(static::$aliases[$root]);
            }
        }
    }

    public static function configure($object, $properties)
    {
        foreach($properties as $name => $value)
        {
            //配置项是数组，可以实现getter setter方法来处理
            $object->$name = $value;
        }

        return $object;
    }

    /**
     * 依赖注入增加组件
     *
     * @param       $type
     * @param array $params
     *
     * @throws Exception
     */
    public static function createObject($type, array $params = [])
    {
        if(is_string($type))
        {
            return static::$container->get($type, $params);
        }
        elseif(is_array($type) && isset($type['class']))
        {
            $class = $type['class'];
            unset($type['class']);
            return static::$container->get($class, $params, $type);
        }
        elseif(is_callable($type, true))
        {
            return call_user_func($type, $params);
        }
        elseif(is_array($type))
        {
            throw new \Exception('Object configuration must be an array containing a "class" element.');
        }
        else
        {
            throw new \Exception("Unsupported configuration type: " . gettype($type));
        }
    }


    /**
     * @return Logger message logger
     */
    public static function getLogger()
    {
        if(self::$_logger !== null)
        {
            return self::$_logger;
        }
        else
        {
            return self::$_logger = static::createObject('ping\\log\\Logger');
        }
    }

    /**
     * Sets the logger object.
     *
     * @param Logger $logger the logger object.
     */
    public static function setLogger($logger)
    {
        self::$_logger = $logger;
    }

    /**
     * 追踪日志(线上产品关闭)
     * 开启追踪日志的目的是为了了解代码的执行流程
     *
     * @param string $message  the message to be logged.
     * @param string $category the category of the message.
     */
    public static function trace($message, $category = 'server')
    {
        if(!empty(Ping::$server->debug) && Ping::$server->debug)
        {
            static::getLogger()
                  ->log($message, Logger::LEVEL_TRACE, $category);
        }
    }

    /**
     * 错误日志
     * 记录程序运行过程中不可恢复的错误
     *
     * @param string $message  the message to be logged.
     * @param string $category the category of the message.
     */
    public static function error($message, $category = 'server')
    {
        static::getLogger()
              ->log($message, Logger::LEVEL_ERROR, $category);
    }

    /**
     * 警告日志.
     *
     * @param string $message  the message to be logged.
     * @param string $category the category of the message.
     */
    public static function warning($message, $category = 'server')
    {
        static::getLogger()
              ->log($message, Logger::LEVEL_WARNING, $category);
    }

    /**
     * 信息日志.
     * 记录一些比较重要的操作( 比如管理员登陆等)
     *
     * @param string $message  the message to be logged.
     * @param string $category the category of the message.
     */
    public static function info($message, $category = 'server')
    {
        static::getLogger()
              ->log($message, Logger::LEVEL_INFO, $category);
    }

    /**
     * 标志分析代码的开始,必须配置分析结束为止
     * 嵌套必须合法
     * ~~~
     * \Ping::beginProfile('block1');
     * // some code to be profiled
     *     \Ping::beginProfile('block2');
     *     // some other code to be profiled
     *     \Ping::endProfile('block2');
     * \Ping::endProfile('block1');
     * ~~~
     *
     * @param string $token    token for the code block
     * @param string $category the category of this log message
     *
     * @see endProfile()
     */
    public static function beginProfile($token, $category = 'application')
    {
        static::getLogger()
              ->log($token, Logger::LEVEL_PROFILE_BEGIN, $category);
    }

    /**
     * 性能分析结束块标志.
     *
     * @param string $token    token for the code block
     * @param string $category the category of this log message
     *
     * @see beginProfile()
     */
    public static function endProfile($token, $category = 'application')
    {
        static::getLogger()
              ->log($token, Logger::LEVEL_PROFILE_END, $category);
    }
}

Ping::$classMap = [
    'ping\base\Callback'     => PING_PATH . '/base/Callback.php',
    'ping\base\Ctrl'         => PING_PATH . '/base/Ctrl.php',
    'ping\base\Dao'          => PING_PATH . '/base/Dao.php',
    'ping\base\Object'       => PING_PATH . '/base/Object.php',
    'ping\base\Pdo'          => PING_PATH . '/base/Pdo.php',
    'ping\base\Server'       => PING_PATH . '/base/Server.php',
    'ping\di\Container'      => PING_PATH . '/di/Container.php',
    'ping\di\Instance'       => PING_PATH . '/di/Instance.php',
    'ping\di\ServiceLocator' => PING_PATH . '/di/ServiceLocator.php',
    'ping\swoole\Server'     => PING_PATH . '/swoole/Server.php',
    'ping\swoole\Callback'   => PING_PATH . '/swoole/Callback.php',
];

\spl_autoload_register(['Ping', 'autoload']);
Ping::$container = new ping\di\Container;
