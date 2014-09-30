<?php
namespace ping\di;

use ping\base\Object;
use ReflectionClass;

class Container extends Object
{
    private $_singletons = [];
    private $_definitions = [];
    private $_params = [];
    private $_reflections = [];
    private $_dependencies = [];


    public function get($class, $params = [], $config = [])
    {
        if(isset($this->_singletons[$class]))
        {
            // singleton
            return $this->_singletons[$class];
        }
        elseif(!isset($this->_definitions[$class]))
        {
            return $this->build($class, $params, $config);
        }

        $definition = $this->_definitions[$class];

        if(is_callable($definition, true))
        {
            $params = $this->resolveDependencies($this->mergeParams($class, $params));
            $object = call_user_func($definition, $this, $params, $config);
        }
        elseif(is_array($definition))
        {
            $concrete = $definition['class'];
            unset($definition['class']);

            $config = array_merge($definition, $config);
            $params = $this->mergeParams($class, $params);

            if($concrete === $class)
            {
                $object = $this->build($class, $params, $config);
            }
            else
            {
                $object = $this->get($concrete, $params, $config);
            }
        }
        elseif(is_object($definition))
        {
            return $this->_singletons[$class] = $definition;
        }
        else
        {
            throw new \Exception("Unexpected object definition type: " . gettype($definition));
        }

        if(array_key_exists($class, $this->_singletons))
        {
            // singleton
            $this->_singletons[$class] = $object;
        }

        return $object;
    }

    public function set($class, $definition = [], array $params = [])
    {
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);
        $this->_params[$class] = $params;
        unset($this->_singletons[$class]);
        return $this;
    }

    public function setSingleton($class, $definition = [], array $params = [])
    {
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);;
        $this->_params[$class] = $params;
        $this->_singletons[$class] = null;
        return $this;
    }

    public function has($class)
    {
        return isset($this->_singletons[$class]);
    }

    public function hasSingleton($class, $checkInstance = false)
    {
        return $checkInstance ? isset($this->_singletons[$class]) : array_key_exists($class, $this->_singletons);
    }

    public function clear($class)
    {
        unset($this->_definitions[$class], $this->_singletons[$class]);
    }

    protected function normalizeDefinition($class, $definition)
    {
        if(empty($definition))
        {
            return ['class' => $class];
        }
        elseif(is_string($definition))
        {
            return ['class' => $definition];
        }
        elseif(is_callable($definition, true) || is_object($definition))
        {
            return $definition;
        }
        elseif(is_array($definition))
        {
            if(!isset($definition['class']))
            {
                if(strpos($class, '\\') !== false)
                {
                    $definition['class'] = $class;
                }
                else
                {
                    throw new \Exception("A class definition requires a \"class\" member.");
                }
            }
            return $definition;
        }
        else
        {
            throw new \Exception("Unsupported definition type for \"$class\": " . gettype($definition));
        }
    }

    public function getDefinitions()
    {
        return $this->_definitions;
    }

    protected function build($class, $params, $config)
    {
        /** @var ReflectionClass $reflection */
        list ($reflection, $dependencies) = $this->getDependencies($class);

        foreach($params as $index => $param)
        {
            $dependencies[$index] = $param;
        }
        $dependencies = $this->resolveDependencies($dependencies, $reflection);

        if(!empty($config) && !empty($dependencies) && is_a($class, 'ping\base\Object', true))
        {
            // set $config as the last parameter (existing one will be overwritten)
            $dependencies[count($dependencies) - 1] = $config;
            return $reflection->newInstanceArgs($dependencies);
        }
        else
        {
            $object = $reflection->newInstanceArgs($dependencies);
            foreach($config as $name => $value)
            {
                $object->$name = $value;
            }
            return $object;
        }
    }

    protected function mergeParams($class, $params)
    {
        if(empty($this->_params[$class]))
        {
            return $params;
        }
        elseif(empty($params))
        {
            return $this->_params[$class];
        }
        else
        {
            $ps = $this->_params[$class];
            foreach($params as $index => $value)
            {
                $ps[$index] = $value;
            }
            return $ps;
        }
    }

    protected function getDependencies($class)
    {
        if(isset($this->_reflections[$class]))
        {
            return [$this->_reflections[$class], $this->_dependencies[$class]];
        }

        $dependencies = [];
        $reflection = new ReflectionClass($class);

        $constructor = $reflection->getConstructor();
        if($constructor !== null)
        {
            foreach($constructor->getParameters() as $param)
            {
                if($param->isDefaultValueAvailable())
                {
                    $dependencies[] = $param->getDefaultValue();
                }
                else
                {
                    $c = $param->getClass();
                    $dependencies[] = Instance::of($c === null ? null : $c->getName());
                }
            }
        }

        $this->_reflections[$class] = $reflection;
        $this->_dependencies[$class] = $dependencies;

        return [$reflection, $dependencies];
    }

    protected function resolveDependencies($dependencies, $reflection = null)
    {
        foreach($dependencies as $index => $dependency)
        {
            if($dependency instanceof Instance)
            {
                if($dependency->id !== null)
                {
                    $dependencies[$index] = $this->get($dependency->id);
                }
                elseif($reflection !== null)
                {
                    $name = $reflection->getConstructor()
                                       ->getParameters()[$index]->getName();
                    $class = $reflection->getName();
                    throw new \Exception("Missing required parameter \"$name\" when instantiating
                    \"$class\".");
                }
            }
        }
        return $dependencies;
    }
}
