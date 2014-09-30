<?php
/**
 * 配置
 */
return [
    'name'           => 'app name',
    'basePath'       => realpath(__DIR__ . '/..'),
    'charset'        => 'utf8',
    'language'       => 'en',
    'sourceLanguage' => 'en-US',
    'debug' => false,
    /**
     * ==start
     * namespace config,can change the app path

     */
    //    'appNamespace'     => 'app',
    //    'ctrlNamespace'    => 'ctrl',
    //    'serviceNamespace' => 'service',
    //    'daoNamespace'     => 'dao',
    //    'entityNamespace'  => 'entity',
    //    'commonNamespace'  => 'common',
    /**
     * ==end
     */
    'components'     => [
        //可以额外添加pdoSlave,相同配置
        'pdo' => [
            'class'    => 'ping\\base\Pdo',
            'dsn'      => 'mysql:host=127.0.0.1;dbname=test',
            'db'       => 'test',
            'user'     => 'root',
            'password' => 'test',
            'pconnect'  => false,
        ],
        'pdo_test' => [
            'class'    => 'ping\\base\Pdo',
            'dsn'      => 'mysql:host=127.0.0.1;dbname=test',
            'db'       => 'test',
            'user'     => 'root',
            'password' => 'test',
            'pconnect'  => false,
        ]
    ],
    'swoole'         => include(__DIR__ . DIRECTORY_SEPARATOR . 'swoole.php'),
    'params'         => include(__DIR__ . DIRECTORY_SEPARATOR . 'params.php'),
];
