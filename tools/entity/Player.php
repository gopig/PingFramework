<?php
/**
 * generate by auto.
 * Time: 2014-09-15 15:00:54
 */
namespace entity;
class Player
{
    const TABLE_NAME = 'player';
    public $playerId = 0;
    public $devId = '';
    public $openId = '';
    public $accessToken = '';
    public $platform = 0;
    public $lastLoginRole = 0;
    public $lastLoginTime = '0000-00-00 00:00:00';
    public $ctime = '0000-00-00 00:00:00';
}