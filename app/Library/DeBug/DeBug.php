<?php
/**
 * @desc 公共工具类
 *
 * Created by PhpStorm.
 * User: lixupeng
 * Date: 16/2/23
 * Time: 下午3:39
 */

namespace App\Library\DeBug;

class DeBug {

    static private $_data = [];

    public static function getContent($id = '')
    {
        try {
            if (!empty($id)) {
                return empty(self::$_data[$id]) ? "" : print_r(self::$_data[$id], true);
            } else {
                return print_r(self::$_data, true);
            }
        } catch (\Exception $e){
            return 'Get Debug Content Exception!';
        }
    }

    public static function setData($id,$data)
    {
        self::$_data[$id] = $data;
    }

    public static function delData($id)
    {
        unset(self::$_data[$id]);
    }
}