#!/bin/sh
# name:
# desc: 重启tcp server
#
#
PHP=php
SERVER=/data/www/ping-xsanguo/webroot/swoole.php

IFSBAK="$IFS"
#设置IFS变量为换行符
IFS='
'
for line in  `ps -ef|grep swoole.php|grep -v grep`
do
	ppid=`echo $line|awk '{print $3}'`
	pid=`echo $line|awk '{print $2}'`
	if [ $ppid -eq 1 ]; then
		echo "swoole.php pid is:$pid, shutdown"
		kill $pid
		break
	fi
	kill $pid

done
IFS=$IFSBAK
while [ 0 -eq 0 ] ;
do
	out=`eval "ps -ef|grep swoole.php|grep -v grep"`
	if [ -z "$out" ];
	then
		break;
        fi
done

echo "startup server: $PHP $SERVER"
$PHP $SERVER
