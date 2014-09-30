<?php
/**
 * generate by auto.
 * Time: 2014-09-15 15:00:54
 */
namespace entity;
class Role
{
    const TABLE_NAME = 'role';
    public $roleId = 0;
    public $playerId = 0;
    public $roleName = '';
    public $roleSex = 0;
    public $avatarId = '';
    public $baseId = 0;
    public $life = 0;
    public $power = 0;
    public $attack = 0;
    public $defense = 0;
    public $crit = 0;
    public $critDamage = 0;
    public $lifeNow = 0;
    public $powerNow = 0;
    public $energy = 0;
    public $level = 1;
    public $exp = 0;
    public $gold = 0;
    public $vip = 0;
    public $banLoginStatus = 0;
    public $banLoginEndTime = '1970-01-01 00:00:00';
    public $registerTime = '1970-01-01 00:00:00';
    public $registerIp = '127.0.0.1';
    public $lastLoginTime = '1970-01-01 00:00:00';
    public $lastLoginIp = '127.0.0.1';
}