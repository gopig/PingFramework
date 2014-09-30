<?php

namespace ping\di;

use Ping;

class Instance
{
    public $id;

    protected function __construct($id)
    {
        $this->id = $id;
    }

    public static function of($id)
    {
        return new static($id);
    }

    public static function ensure($reference, $type = null, $container = null)
    {
        if ($reference instanceof $type) {
            return $reference;
        } elseif (empty($reference)) {
            throw new \Exception('The required component is not specified.');
        }

        if (is_string($reference)) {
            $reference = new static($reference);
        }

        if ($reference instanceof self) {
            $component = $reference->get($container);
            if ($component instanceof $type || $type === null) {
                return $component;
            } else {
                throw new \Exception('"' . $reference->id . '" refers to a ' . get_class($component) . "
                component. $type is expected.");
            }
        }

        $valueType = is_object($reference) ? get_class($reference) : gettype($reference);
        throw new \Exception("Invalid data type: $valueType. $type is expected.");
    }

    public function get($container = null)
    {
        if ($container) {
            return $container->get($this->id);
        }
        if (Ping::$server && Ping::$server->has($this->id)) {
            return Ping::$server->get($this->id);
        } else {
            return Ping::$container->get($this->id);
        }
    }
}
