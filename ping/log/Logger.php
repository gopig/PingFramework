<?php
namespace ping\log;

use Ping;
use ping\base\Object;

class Logger extends Object
{
    /**
     * Error message level. An error message is one that indicates the abnormal termination of the
     * application and may require developer's handling.
     */
    const LEVEL_ERROR = 0x01;
    /**
     * Warning message level. A warning message is one that indicates some abnormal happens but
     * the application is able to continue to run. Developers should pay attention to this message.
     */
    const LEVEL_WARNING = 0x02;
    /**
     * Informational message level. An informational message is one that includes certain information
     * for developers to review.
     */
    const LEVEL_INFO = 0x04;
    /**
     * Tracing message level. An tracing message is one that reveals the code execution flow.
     */
    const LEVEL_TRACE = 0x08;
    /**
     * Profiling message level. This indicates the message is for profiling purpose.
     */
    const LEVEL_PROFILE = 0x40;
    /**
     * Profiling message level. This indicates the message is for profiling purpose. It marks the
     * beginning of a profiling block.
     */
    const LEVEL_PROFILE_BEGIN = 0x50;
    /**
     * Profiling message level. This indicates the message is for profiling purpose. It marks the
     * end of a profiling block.
     */
    const LEVEL_PROFILE_END = 0x60;


    /**
     * @var array logged messages. This property is managed by [[log()]] and [[flush()]].
     * Each log message is of the following structure:
     * ~~~
     * [
     *   [0] => message (mixed, can be a string or some complex data, such as an exception object)
     *   [1] => level (integer)
     *   [2] => category (string)
     *   [3] => timestamp (float, obtained by microtime(true))
     *   [4] => traces (array, debug backtrace, contains the application code call stacks)
     * ]
     * ~~~
     */
    public $messages = [];
    /**
     * @var integer how many messages should be logged before they are flushed from memory and sent to targets.
     * Defaults to 1000, meaning the [[flush]] method will be invoked once every 1000 messages logged.
     * Set this property to be 0 if you don't want to flush messages until the application terminates.
     * This property mainly affects how much memory will be taken by the logged messages.
     * A smaller value means less memory, but will increase the execution time due to the overhead of [[flush()]].
     */
    //public $flushInterval = 1000;
    public $flushInterval = 1;
    /**
     * @var integer how much call stack information (file name and line number) should be logged for each message.
     * If it is greater than 0, at most that number of call stacks will be logged. Note that only application
     * call stacks are counted.
     */
    public $traceLevel = 0;


    /**
     * Initializes the logger by registering [[flush()]] as a shutdown function.
     */
    public function init()
    {
        parent::init();
        register_shutdown_function([$this, 'flush'], true);
    }

    public function log($message, $level, $category = 'application')
    {
        $time = microtime(true);
        $traces = [];
        if($this->traceLevel > 0)
        {
            $count = 0;
            $ts = debug_backtrace();
            array_pop($ts); // remove the last trace since it would be the entry script, not very useful
            foreach($ts as $trace)
            {
                if(isset($trace['file'], $trace['line']) && strpos($trace['file'], MIAO_PATH) !== 0)
                {
                    unset($trace['object'], $trace['args']);
                    $traces[] = $trace;
                    if(++$count >= $this->traceLevel)
                    {
                        break;
                    }
                }
            }
        }
        $this->messages[] = [$message, $level, $category, $time, $traces];
        if($this->flushInterval > 0 && count($this->messages) >= $this->flushInterval)
        {
            $this->flush();
        }
    }

    public function flush($final = false)
    {
        $fileLogger = Ping::createObject('ping\\log\\FileTarget');
        $fileLogger->collect($this->messages, $final);
        $this->messages = [];
    }

    public function getElapsedTime()
    {
        return microtime(true) - PING_BEGIN_TIME;
    }

    public function getProfiling($categories = [], $excludeCategories = [])
    {
        $timings = $this->calculateTimings($this->messages);
        if(empty($categories) && empty($excludeCategories))
        {
            return $timings;
        }

        foreach($timings as $i => $timing)
        {
            $matched = empty($categories);
            foreach($categories as $category)
            {
                $prefix = rtrim($category, '*');
                if(strpos($timing['category'], $prefix) === 0 && ($timing['category'] === $category || $prefix !== $category))
                {
                    $matched = true;
                    break;
                }
            }

            if($matched)
            {
                foreach($excludeCategories as $category)
                {
                    $prefix = rtrim($category, '*');
                    foreach($timings as $i => $timing)
                    {
                        if(strpos($timing['category'], $prefix) === 0 && ($timing['category'] === $category || $prefix !== $category))
                        {
                            $matched = false;
                            break;
                        }
                    }
                }
            }

            if(!$matched)
            {
                unset($timings[$i]);
            }
        }

        return array_values($timings);
    }


    public function calculateTimings($messages)
    {
        $timings = [];
        $stack = [];

        foreach($messages as $i => $log)
        {
            list($token, $level, $category, $timestamp, $traces) = $log;
            $log[5] = $i;
            if($level == Logger::LEVEL_PROFILE_BEGIN)
            {
                $stack[] = $log;
            }
            elseif($level == Logger::LEVEL_PROFILE_END)
            {
                if(($last = array_pop($stack)) !== null && $last[0] === $token)
                {
                    $timings[$last[5]] = [
                        'info'      => $last[0],
                        'category'  => $last[2],
                        'timestamp' => $last[3],
                        'trace'     => $last[4],
                        'level'     => count($stack),
                        'duration'  => $timestamp - $last[3],
                    ];
                }
            }
        }

        ksort($timings);

        return array_values($timings);
    }


    public static function getLevelName($level)
    {
        static $levels = [
            self::LEVEL_ERROR         => 'error',
            self::LEVEL_WARNING       => 'warning',
            self::LEVEL_INFO          => 'info',
            self::LEVEL_TRACE         => 'trace',
            self::LEVEL_PROFILE_BEGIN => 'profile begin',
            self::LEVEL_PROFILE_END   => 'profile end',
        ];

        return isset($levels[$level]) ? $levels[$level] : 'unknown';
    }
}
