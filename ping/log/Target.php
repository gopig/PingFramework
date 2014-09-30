<?php
namespace ping\log;

use ping\base\Object;

abstract class Target extends Object
{
    public $enabled = true;
    public $categories = [];
    public $except = [];
    public $logVars = ['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'];
    public $prefix;
    public $exportInterval = 1000;
    public $messages = [];

    private $_levels = 0;

    abstract public function export();

    public function collect($messages, $final)
    {
        $this->messages = array_merge($this->messages, $this->filterMessages($messages, $this->getLevels(), $this->categories, $this->except));
        $count = count($this->messages);
        if($count > 0 && ($final || $this->exportInterval > 0 && $count >= $this->exportInterval))
        {
            if(($context = $this->getContextMessage()) !== '')
            {
                $this->messages[] = [$context, Logger::LEVEL_INFO, 'application', YII_BEGIN_TIME];
            }
            $this->export();
            $this->messages = [];
        }
    }

    protected function getContextMessage()
    {
        $context = [];
        foreach($this->logVars as $name)
        {
            if(!empty($GLOBALS[$name]))
            {
                $context[] = "\${$name} = " . var_export($GLOBALS[$name], true);
            }
        }

        return implode("\n\n", $context);
    }

    public function getLevels()
    {
        return $this->_levels;
    }

    public function setLevels($levels)
    {
        static $levelMap = [
            'error'   => Logger::LEVEL_ERROR,
            'warning' => Logger::LEVEL_WARNING,
            'info'    => Logger::LEVEL_INFO,
            'trace'   => Logger::LEVEL_TRACE,
            'profile' => Logger::LEVEL_PROFILE,
        ];
        if(is_array($levels))
        {
            $this->_levels = 0;
            foreach($levels as $level)
            {
                if(isset($levelMap[$level]))
                {
                    $this->_levels |= $levelMap[$level];
                }
                else
                {
                    throw new \Exception("Unrecognized level: $level");
                }
            }
        }
        else
        {
            $this->_levels = $levels;
        }
    }

    public static function filterMessages($messages, $levels = 0, $categories = [], $except = [])
    {
        foreach($messages as $i => $message)
        {
            if($levels && !($levels & $message[1]))
            {
                unset($messages[$i]);
                continue;
            }

            $matched = empty($categories);
            foreach($categories as $category)
            {
                if($message[2] === $category || substr($category, -1) === '*' && strpos($message[2], rtrim($category, '*')) === 0)
                {
                    $matched = true;
                    break;
                }
            }

            if($matched)
            {
                foreach($except as $category)
                {
                    $prefix = rtrim($category, '*');
                    if(strpos($message[2], $prefix) === 0 && ($message[2] === $category || $prefix !== $category))
                    {
                        $matched = false;
                        break;
                    }
                }
            }

            if(!$matched)
            {
                unset($messages[$i]);
            }
        }

        return $messages;
    }

    public function formatMessage($message)
    {
        list($text, $level, $category, $timestamp) = $message;
        $level = Logger::getLevelName($level);
        if(!is_string($text))
        {
            $text = var_export($text, true);
        }

        $prefix = $this->prefix ? call_user_func($this->prefix, $message) : $this->getMessagePrefix($message);

        return date('Y-m-d H:i:s', $timestamp) . " {$prefix}[$level][$category] $text";
    }

    public function getMessagePrefix($message)
    {
        //TODO get client ip
        //TODO get fd
        $ip = '127.0.0.1';
        $fd = '0';
        return "[$ip][$fd]";
    }
}
