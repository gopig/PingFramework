<?php
namespace ping\base;

use Ping;

class Pdo extends Object
{
    private $pdo;
    private $table;
    private $entity;

    public $dsn;
    public $db;
    public $user;
    public $password;
    public $pconnect = false;
    public $attr = [];

    private function connect()
    {
        $charset = Ping::$server->charset;
        return new \PDO($this->dsn, $this->user, $this->password, [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$charset}';",
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_PERSISTENT         => $this->pconnect
        ]);

    }

    public function __construct()
    {
        parent::__construct();
    }

    public function ping()
    {
        if(empty($this->pdo))
        {
            $this->pdo = $this->connect();
        }
        else
        {
            try
            {
                $this->pdo->getAttribute(\PDO::ATTR_SERVER_INFO);
            }
            catch(\Exception $e)
            {
                if($e->getCode() == 'HY000')
                {
                    $this->pdo = $this->connect();
                }
                else
                {
                    throw $e;
                }
            }
        }
        return $this->pdo;
    }

    public function close()
    {
        if(!$this->pconnect)
        {
            $this->pdo = null;
        }
    }


    public function setTable($tableName)
    {
        if(empty($tableName))
        {
            return;
        }
        $this->table = $tableName;
    }

    public function getTable()
    {
        if(empty($this->table))
        {
            $classRef = new \ReflectionClass($this->entity);
            $this->table = $classRef->getConstant('TABLE_NAME');
        }

        return $this->table;
    }

    public function setEntity($className)
    {
        if(!empty($className) && $this->entity != $className)
        {
            $this->entity = $className;
            $this->table = null;
        }
    }

    public function getEntity()
    {
        return $this->entity;
    }

    public function getLibName()
    {
        return "`{$this->db}`.`{$this->getTable()}`";
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    public function setPdo($pdo)
    {
        $this->pdo = $pdo;
    }

    public function add($entity, $fields, $onDuplicate = null)
    {
        $strFields = '`' . implode('`,`', $fields) . '`';
        $strValues = ':' . implode(', :', $fields);

        $query = "INSERT INTO {$this->getLibName()} ({$strFields}) VALUES ({$strValues})";

        if(!empty($onDuplicate))
        {
            $query .= 'ON DUPLICATE KEY UPDATE ' . $onDuplicate;
        }
        try
        {

            $statement = $this->pdo->prepare($query);
            $params = [];

            foreach($fields as $field)
            {
                $params[$field] = $entity->$field;
            }
            $statement->execute($params);
        }
        catch(\PDOException $e)
        {
            echo 'Insert failed: ' . $e->getMessage();
        }

        return $this->pdo->lastInsertId();
    }

    public function addMulti($entitys, $fields)
    {
        $items = [];
        $params = [];

        foreach($entitys as $index => $entity)
        {
            $items[] = '(:' . implode($index . ', :', $fields) . $index . ')';

            foreach($fields as $field)
            {
                $params[$field . $index] = $entity->$field;
            }
        }

        $query = "INSERT INTO {$this->getLibName()} (`" . implode('`,`', $fields) . "`) VALUES " . implode(',', $items);
        $statement = $this->pdo->prepare($query);
        return $statement->execute($params);
    }

    public function replace($entity, $fields)
    {
        $strFields = '`' . implode('`,`', $fields) . '`';
        $strValues = ':' . implode(', :', $fields);

        $query = "REPLACE INTO {$this->getLibName()} ({$strFields}) VALUES ({$strValues})";
        $statement = $this->pdo->prepare($query);
        $params = [];

        foreach($fields as $field)
        {
            $params[$field] = $entity->$field;
        }
        $statement->execute($params);
        return $this->pdo->lastInsertId();
    }

    public function update($fields, $params, $where, $change = false)
    {
        if($change)
        {
            $updateFields = array_map(__CLASS__ . '::changeFieldMap', $fields);
        }
        else
        {
            $updateFields = array_map(__CLASS__ . '::updateFieldMap', $fields);
        }

        $strUpdateFields = implode(',', $updateFields);
        $query = "UPDATE {$this->getLibName()} SET {$strUpdateFields} WHERE {$where}";
        $statement = $this->pdo->prepare($query);
        return $statement->execute($params);
    }

    public function fetchValue($where = '1', $params = null, $fields = '*')
    {
        $query = "SELECT {$fields} FROM {$this->getLibName()} WHERE {$where} limit 1";
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    public function fetchArray($where = '1', $params = null, $fields = '*', $orderBy = null, $limit = null)
    {
        $query = "SELECT {$fields} FROM {$this->getLibName()} WHERE {$where}";

        if($orderBy)
        {
            $query .= " ORDER BY {$orderBy}";
        }

        if($limit)
        {
            $query .= " limit {$limit}";
        }

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        return $statement->fetchAll();
    }

    public function fetchCol($where = '1', $params = null, $fields = '*', $orderBy = null, $limit = null)
    {
        $results = $this->fetchArray($where, $params, $fields, $orderBy, $limit);
        return empty($results) ? [] : array_map('reset', $results);
    }

    public function fetchAll($where = '1', $params = null, $fields = '*', $orderBy = null, $limit = null)
    {
        $query = "SELECT {$fields} FROM {$this->getLibName()} WHERE {$where}";

        if($orderBy)
        {
            $query .= " order by {$orderBy}";
        }

        if($limit)
        {
            $query .= " limit {$limit}";
        }
        $statement = $this->pdo->prepare($query);

        if(!$statement->execute($params))
        {
            throw new \Exception('data base error');
        }

        $statement->setFetchMode(\PDO::FETCH_CLASS, $this->entity);
        return $statement->fetchAll();
    }

    public function fetchEntity($where = '1', $params = null, $fields = '*', $orderBy = null)
    {
        $query = "SELECT {$fields} FROM {$this->getLibName()} WHERE {$where}";

        if($orderBy)
        {
            $query .= " order by {$orderBy}";
        }

        $query .= " limit 1";
        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        $statement->setFetchMode(\PDO::FETCH_CLASS, $this->entity);
        return $statement->fetch();
    }

    public function fetchCount($where = '1', $pk = "*")
    {
        $query = "SELECT count({$pk}) as count FROM {$this->getLibName()} WHERE {$where}";
        $statement = $this->pdo->prepare($query);
        $statement->execute();
        $result = $statement->fetch();
        return $result["count"];
    }

    //$params = [] php5.3.6 报语法错误 change by ahuo 2013-11-05 14:23
    public function remove($where, $params = [])
    {
        if(empty($where))
        {
            return false;
        }

        $query = "DELETE FROM {$this->getLibName()} WHERE {$where}";
        $statement = $this->pdo->prepare($query);
        return $statement->execute($params);
    }

    public function flush()
    {
        $query = "TRUNCATE {$this->getLibName()}";
        $statement = $this->pdo->prepare($query);
        return $statement->execute();
    }

    public static function updateFieldMap($field)
    {
        return '`' . $field . '`=:' . $field;
    }

    public static function changeFieldMap($field)
    {
        return '`' . $field . '`=`' . $field . '`+:' . $field;
    }

    public function fetchBySql($sql)
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        return $statement->fetchAll();
    }


}
