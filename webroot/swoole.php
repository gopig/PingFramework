<?php
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(__DIR__));

include(ROOT_PATH . DS . 'ping' . DS . 'Ping.php');

$config = include(ROOT_PATH . DS . 'config' . DS . 'config.php');
//Ping::beginProfile('test');
(new ping\swoole\Server($config))->run();
//Ping::endProfile('test');
