<?php
namespace ping\base;

class Factory
{
    private static $instances = [];

    public static function getInstance($className, $params = null)
    {
        $keyName = $className;
        if(!empty($params['_prefix']))
        {
            $keyName .= $params['_prefix'];
        }
        if(isset(self::$instances[$keyName]))
        {
            return self::$instances[$keyName];
        }
        if(!\class_exists($className))
        {
            throw new \Exception("no class {$className}");
        }
        if(empty($params))
        {
            self::$instances[$keyName] = new $className();
        }
        else
        {
            self::$instances[$keyName] = new $className($params);
        }
        return self::$instances[$keyName];
    }
}
