<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 14-8-22
 * Time: 下午6:28
 */
//phpinfo();
//echo bin2hex($i);
var_dump(bin2hex(pack("N1", 7)));
$bin = pack("N1", 7);

$d = unpack("N1ele", $bin);

print_r($d);
