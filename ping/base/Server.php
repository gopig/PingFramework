<?php
/**
 * @desc
 * @author
 * @version
 */

namespace ping\base;


use Ping;
use ping\di\ServiceLocator;

abstract class Server extends ServiceLocator
{
    //成员变量(不需要严格检查值的合法性)
    public $name = "application name";//应用名称
    public $charset = 'UTF-8';
    public $language = 'zh-CN';
    public $sourceLanguage = 'en';
    public $debug = false;
    //额外参数
    public $params = [];

    private $_basePath;//应用根目录,即appNamespace上级目录
    private $_runtime = 'runtime';
    //app(namespace+path)
    private $_appNamespace = 'app';//和实际路径名称一致
    private $_ctrlNamespace = 'app\\ctrl';
    private $_serviceNamespace = 'app\\service';
    private $_daoNamespace = 'app\\dao';
    private $_entityNamespace = 'app\\entity';
    private $_commonNamespace = 'app\\common';


    abstract public function handleRequest();

    public function __construct($config = [])
    {
        Ping::$server = $this;
        $this->preInit($config);
        parent::__construct($config);
    }

    public function preInit(&$config)
    {
        if(isset($config['basePath']))
        {
            $this->setBasePath($config['basePath']);
            unset($config['basePath']);
        }
        else
        {
            throw new \Exception("The 'basePath' configuration for the Server must be set");
        }
        $appNamespace = isset($config['appNamespace']) ? $config['appNamespace'] : $this->getAppNamespace();
        $this->setAppNamespace($appNamespace);
        //组件引入
        foreach($this->coreComponents() as $id => $component)
        {
            if(!isset($config['components'][$id]))
            {
                $config['components'][$id] = $component;
            }
            elseif(is_array($config['components'][$id]) && !isset
                ($config['components'][$id]['class'])
            )
            {
                $config['components'][$id]['class'] = $component['class'];
            }
        }
    }

    public function run()
    {
        //FIXME  继续完善
        return $this->handleRequest();
    }

    public function getBasePath()
    {
        //basePath 必须在配置文件中设定
        return $this->_basePath;
    }

    public function setBasePath($path)
    {
        $path = Ping::getAlias($path);
        $p = realpath($path);
        if($p !== false && is_dir($p))
        {
            $this->_basePath = $p;
        }
        else
        {
            throw new \Exception("The directory does not exist: $path");
        }
    }

    public function getRuntimePath()
    {
        if($this->_runtime === 'runtime')
        {
            $this->setRuntimePath($this->_runtime);
        }
        return $this->_runtime;
    }

    public function setRuntimePath($runtimePath)
    {
        $runtimePath = ltrim(str_replace([
            '/',
            '\\'
        ], DIRECTORY_SEPARATOR, $runtimePath), DIRECTORY_SEPARATOR);
        $this->_runtime = $this->getBasePath() . DIRECTORY_SEPARATOR . $runtimePath;
    }

    public function getAppNamespace()
    {
        return $this->_appNamespace;
    }

    public function setAppNamespace($namespace)
    {
        $namespace = rtrim(str_replace('/', '\\', $namespace), '\\');
        $this->_appNamespace = $namespace;
        Ping::setAlias('@app', $this->getBasePath() . DIRECTORY_SEPARATOR . $this->_appNamespace);
    }

    public function getCtrlNamespace()
    {
        return $this->_ctrlNamespace;
    }

    public function setCtrlNamespace($namespace)
    {
        $namespace = rtrim(str_replace('/', '\\', $namespace), '\\');
        $this->_ctrlNamespace = $this->_appNamespace . '\\' . $namespace;
    }

    public function getServiceNamespace()
    {
        return $this->_serviceNamespace;
    }

    public function setServiceNamespace($namespace)
    {
        $namespace = rtrim(str_replace('/', '\\', $namespace), '\\');
        $this->_serviceNamespace = $this->_appNamespace . '\\' . $namespace;
    }

    public function getDaoNamespace()
    {
        return $this->_daoNamespace;
    }

    public function setDaoNamespace($namespace)
    {
        $namespace = rtrim(str_replace('/', '\\', $namespace), '\\');
        $this->_daoNamespace = $this->_appNamespace . '\\' . $namespace;
    }

    public function getEntityNamespace()
    {
        return $this->_entityNamespace;
    }

    public function setEntityNamespace($namespace)
    {
        $namespace = rtrim(str_replace('/', '\\', $namespace), '\\');
        $this->_entityNamespace = $this->_appNamespace . '\\' . $namespace;
    }

    public function getCommonNamespace()
    {
        return $this->_commonNamespace;
    }

    public function setCommonNamespace($namespace)
    {
        $namespace = rtrim(str_replace('/', '\\', $namespace), '\\');
        $this->_commonNamespace = $this->_appNamespace . '\\' . $namespace;
    }

    public function coreComponents()
    {
        return [
            'pdo' => ['class' => 'ping\base\Pdo'],
        ];
    }

    //TODO code some component method
    public function getPdo($pdo = 'pdo')
    {
        return $this->get($pdo);
    }

}