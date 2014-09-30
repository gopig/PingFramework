<?php
namespace ping\log;

use Ping;
use ping\base\Object;

class Dispatcher extends Object
{
    public $targets = [];

    private $_logger;


    public function __construct($config = [])
    {
        if (isset($config['logger'])) {
            $this->setLogger($config['logger']);
            unset($config['logger']);
        }
        // connect logger and dispatcher
        $this->getLogger();

        parent::__construct($config);
    }

    public function init()
    {
        parent::init();

        foreach ($this->targets as $name => $target) {
            if (!$target instanceof Target) {
                $this->targets[$name] = Ping::createObject($target);
            }
        }
    }

    public function getLogger()
    {
        if ($this->_logger === null) {
            $this->setLogger(Ping::getLogger());
        }
        return $this->_logger;
    }

    public function setLogger($value)
    {
        $this->_logger = $value;
        $this->_logger->dispatcher = $this;
    }

    public function getTraceLevel()
    {
        return $this->getLogger()->traceLevel;
    }

    public function setTraceLevel($value)
    {
        $this->getLogger()->traceLevel = $value;
    }

    public function getFlushInterval()
    {
        return $this->getLogger()->flushInterval;
    }

    public function setFlushInterval($value)
    {
        $this->getLogger()->flushInterval = $value;
    }

    public function dispatch($messages, $final)
    {
        foreach ($this->targets as $target) {
            if ($target->enabled) {
                $target->collect($messages, $final);
            }
        }
    }
}
