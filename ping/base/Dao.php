<?php
/**
 * TODO 继续完善优化
 * @desc
 * @author
 * @version
 */

namespace ping\base;

use Ping;

class Dao
{
    private $_entity;
    private static $_pdo = [];

    public function __construct($entity)
    {
        $this->_entity = $entity;
    }

    public function getPdo($pdo = 'pdo')
    {
        if(!empty(self::$_pdo[$pdo]))
        {
            return self::$_pdo[$pdo];
        }
        self::$_pdo[$pdo] = Ping::$server->getPdo($pdo);
        self::$_pdo[$pdo]->setEntity($this->_entity);
        self::$_pdo[$pdo]->ping();
        return self::$_pdo[$pdo];
    }
}