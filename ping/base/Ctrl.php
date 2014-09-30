<?php
/**
 * @desc
 * @author
 * @version
 */

namespace ping\base;


class Ctrl
{
    //TODO 如果子类有特殊处理过程,则处理
    public function _before()
    {
        return true;
    }

    //TODO 如果子类有特殊处理过程，则处理
    public function _after()
    {
        return true;
    }

}