<?php

namespace App\Library;

/**
 * redis的全局统一封装类，目前只有一个redis入口，如果后续要连接多个redis，在这个类里进行扩展
 * Class RedisObj
 */
class RedisObj {

    private static $redisCommon;

    private function __construct()
    {
    }

    public static function instance()
    {
        if (empty(self::$redisCommon)) self::$redisCommon = new RedisCommon();
        return self::$redisCommon;
    }

}