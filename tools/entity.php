<?php
/**
 * generator dao entity class.
 * User: Administrator
 * Date: 14-8-21
 * Time: 上午9:31
 */

$config = [
    'dsn'     => 'mysql:host=127.0.0.1;dbname=xsanguo_dev',
    'user'    => 'root',
    'passwd'  => 'zhituo2014!',
    'charset' => 'utf8',
];
try
{
    $date = date('Y-m-d H:i:s');
    $pdo = new \PDO($config['dsn'], $config['user'], $config['passwd'], array(
        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$config['charset']}';",
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_PERSISTENT         => empty($config['pconnect']) ? false : true
    ));
    $s = 'show tables';
    $tables = $pdo->query($s)
        ->fetchAll(PDO::FETCH_COLUMN);
    if ($tables)
    {
        foreach ($tables as $t)
        {
            $className = ucfirst($t);
            $class = <<<CLSSS
<?php
/**
 * generate by auto.
 * Time: {$date}
 */
namespace app\entity;
class {$className}
{
    const TABLE_NAME = '{$t}';

CLSSS;
            $s = "desc {$t}";
            $desc = $pdo->query($s)
                ->fetchAll(PDO::FETCH_OBJ);
            //Field, Type, Null, Key, Default, Extra
            foreach ($desc as $r)
            {
                $name = '$' . $r->Field;
                $default = null;
                if (is_null($r->Default))
                {
                    $p = '/(\w+)\(\d+\)/';
                    preg_match($p, $r->Type, $m);
                    $type = strtolower($m[1]);
                    switch ($type)
                    {
                        case 'tinyint':
                        case 'bit':
                        case 'bool':
                        case 'smallint':
                        case 'integer':
                        case 'bigint':
                        case 'float':
                        case 'double':
                        case 'real':
                        case 'decimal':
                        case 'dec':
                        case 'numric':
                        case 'int':
                            $default = 0;
                            break;
                        //mediumblob,mediumtext,longblob,longtext,enum,set 忽略
                        case 'tinyblob':
                        case 'tinytext':
                        case 'blob':
                        case 'text':
                        case 'char':
                        case 'varchar':
                            $default = "''";
                            break;
                        case 'datetime':
                            $default = '1987-03-23 00:00:00';
                            break;
                        case 'date':
                            $default = '1987-03-23';
                            break;
                        default:
                            $default = "''";
                    }
                }
                elseif (strlen($r->Default) > 0)
                {
                    $default = is_numeric($r->Default) ? $r->Default : "'{$r->Default}'";
                }
                else
                {
                    $default = "''";
                }
                $class .= <<<CLASS
    public {$name} = {$default};\n
CLASS;

            }
            $class .= <<<CLASS
}
CLASS;
            //写入文件
            $classFile = "entity/{$className}.php";
            $fp = fopen($classFile, 'w');
            if($fp)
            {
                fwrite($fp, $class);
                fclose($fp);
                echo $classFile . " generated\n";
            }else{
                exit("\nopen file {$classFile} failed\n");
            }
        }
    }

}
catch(PDOException $e)
{
    echo $e->getMessage();
}
