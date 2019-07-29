<?php 
namespace App\Library\Hook;

/**
 * @description 
 * @date Mar 13, 2019
 * @author shenxin
 */
class Hook{
    public static function call($hookConfig,$data = array()){
        if($hookConfig && is_array($hookConfig)){
            $hash_temp = array();
            $all_args = func_get_args();
            array_shift($all_args);
            foreach ($hookConfig as $hookKey =>$hookClass){
                if($hookClass && is_string($hookClass)){
                    $hookClassHash = md5($hookClass);
                    $object = isset($hash_temp[$hookClassHash])?$hash_temp[$hookClassHash]:new $hookClass();
                    return call_user_func_array(array($object,'handler'), $all_args);
                }
            }
        }
        return $data;
    }
}