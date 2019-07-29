<?php
/**
 * 合并两个array 类似于PHP的array_merge 本函数可将需插入的array插入到指定的array的指定位置
 *
 * @param array $array原始array
 * @param int $position 原始array的位 即需要插入的位置
 * @param array $insert_array 需要插入的array
 * @return array 合并后的总array
 */
function array_insert(&$array, $position, $insert_array) {
    if (!is_int($position)) {
        $i = 0;
        foreach ($array as $key => $value) {
            if ($key == $position) { $position = $i;break;}
            $i++;
        }
    }
    $first_array = array_splice ($array, 0, $position);
    return array_merge($first_array, $insert_array, $array);
}

/**
 * 数组排序
 * @param unknown_type $array
 * @param unknown_type $keyname
 * @param unknown_type $sortDirection
 * @return return_type
 * @author shenxin
 * @date 2012-5-8下午03:59:44
 * @version V1R6B005
 */
function array_column_sort($array, $keyname, $sortDirection = SORT_ASC)
{
    return __array_sortby_multifields($array, array(
        $keyname => $sortDirection
    ));
}

function __array_sortby_multifields($rowset, $args)
{
    $sortArray = array();
    $sortRule  = '';
    foreach ($args as $sortField => $sortDir) {
        foreach ($rowset as $offset => $row) {
            @$sortArray[$sortField][$offset] = $row[$sortField];
        }
        $sortRule .= '$sortArray[\'' . $sortField . '\'], ' . $sortDir . ', ';
    }
    if (empty($sortArray) || empty($sortRule)) {
        return $rowset;
    }
    eval('array_multisort(' . $sortRule . '$rowset);');
    return $rowset;
}
/**
 * 数组分组
 * @param array $arr
 * @param string $key_field
 */
function array_group_by($arr, $key_field, $mulit = true)
{
    if(empty($arr))return $arr;
    $ret = array();
    foreach ($arr as $row) {
        $key = trim($row[$key_field]);
        if ($mulit) {
            $ret[$key][] = $row;
        } else {
            $ret[$key] = $row;
        }
    }
    return $ret;
}
if (!function_exists('get_array_value')) {
    function get_array_value($array, $key = 'id', $fixed_key = false){
        if (!$array || !is_array($array)) return array();
        $res = array();
        foreach ($array as $k => $a) {
            if(is_array($key)){
                foreach ($key as $find_key=>$find_val){
                    if($fixed_key){
                        $res[$find_val][$k] = $a[$find_val];
                    }else{
                        $res[$find_val][] = $a[$find_val];
                    }
                }
            }else{
                if ($fixed_key) {
                    $res[$k] = $a[$key];
                } else {
                    $res[] = $a[$key];
                }
            }
        }
        return $res;
    }
}
/**
 * 获取数组值 并强制转换成 INT
 * @param array $array
 * @param string $key
 * @param boolean $join
 */
function get_array_value_for_int($array, $key = '', $join = false, $uniq = false, $filter = true, $int = true, $split_arg = ',')
{
    if (empty($array))
        return null;
        $array = !is_array($array) ? array_filter(explode($split_arg, $array)) : (array) $array;
        $res   = array();
        foreach ($array as $a) {
            $val   = empty($key) ? $a : $a[$key];
            $res[] = $int ? to_int($val) : $val;
        }
        if ($filter) {
            $res = array_filter($res);
        }
        $res = $uniq ? array_unique($res) : $res;
        return $join ? (empty($res)?0:join(',', $res)) : $res;
}
/**
 * 合并两个数据相同的数据 二维数组
 * @param array $array
 * @param boolean $keep_key_assoc
 * @return return_type
 * @author shenxin
 * @date 2012-5-4上午09:29:16
 * @version V1R6B005
 */
function array_uniques($array, $keep_key_assoc = false)
{
    $duplicate_keys = array();
    $tmp            = array();
    foreach ($array as $key => $val) {
        if (is_object($val)) {
            $val = (array) $val;
        }
        $ghash = md5(json_encode($val));
        if (!isset($tmp[$ghash])) {
            $tmp[$ghash] = 1;
        } else {
            $duplicate_keys[] = $key;
        }
    }
    $tmp = null;
    foreach ($duplicate_keys as $key) {
        unset($array[$key]);
    }
    $duplicate_keys = null;
    return $keep_key_assoc ? $array : array_values($array);
}

/**
 * 删除key-val数组中的某个key对应的项
 * @param array $data
 * @param string $key
 * @return array $data
 * @author luozhengwang@xin.com
 * @date 2012-5-10 下午1:06:16
 */
function array_remove($data, $key)
{
    if (!array_key_exists($key, $data)) {
        return $data;
    }
    $keys = array_keys($data);
    $index = array_search($key, $keys);
    if ($index !== FALSE) {
        array_splice($data, $index, 1);
    }
    return $data;
}