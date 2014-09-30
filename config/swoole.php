<?php
/**
 * swoole 配置
 */
return [
    'work_num'      => 8,
    'task_work_num' => 2,
    'dispatch_mode' => 2,
    'log_file'      => '/tmp/swoole/swoole.log',
    'callback'      => [
        'class' => 'ping\\swoole\\Callback',
    ],
];
