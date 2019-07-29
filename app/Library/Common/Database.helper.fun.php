<?php
use App\Library\AppError;
use Uxin\Finance\CLib\CLib;

/**
 * @description
 * @date Nov 20, 2017
 * @author shenxin
 */
function log_query_logs($data){
    static $k = 1;
    static $_cache_temp = array();
    static $_cache_temp_lock_check = array();
    //最大MYSQL日志总数 按总数统计
    $allow_max_query_logs = is_production_env()?150:500;
    $key = '_system_query_logs_';
    if(empty($data) || !is_array($data))return ;
    $connectionName = $data['name'];
    $ok_flag = $data['flag'];
    $server = $data['server'];
    $config = config('database.connections');
    //不适合多个从库的情况
    $database_config = $config[$connectionName];
    $server_string = $server=='slave'?'read':'write';
    $server_info = $database_config[$server_string];
    $data['key'] = $k;
    $hash_key = '-n '.$connectionName.' -h'.$server_info['host']. ' -u'.$server_info['username'].' -P'.$server_info['port']." --db ".$server_info['database'];
    $_cache_temp[$hash_key]=isset($_cache_temp[$hash_key])?(++$_cache_temp[$hash_key]):1;
    $data['_key_'] = $hash_key;
    if(isset($_cache_temp_lock_check[$hash_key]) && $ok_flag=='ok'){
        $k++;
        return ;
    }
    $find_num = isset($_cache_temp[$hash_key]) && $_cache_temp[$hash_key]>0?$_cache_temp[$hash_key]:0;
    if($find_num>=$allow_max_query_logs && $ok_flag=='ok'){
        $title_warning = sprintf(' MySQL Query log has exceeded the upper limit %s in this query group, please check whether the program is necessary to adjust.',$allow_max_query_logs);
        $data['waring'] = $title_warning;
        //Notify()->set($title_warning, $data)->send('cc_credit_system_'.$title_warning,500);
        //加锁
        $_cache_temp_lock_check[$hash_key] = 1;
    }
    //计数器
    AppError::append('_system_query_logs_',$data);
    $k++;
}
function get_query_log_array(){
    return AppError::get('_system_query_logs_');
}
function format_out_query_log($querys){
    return get_query_log(false,$querys);
}
/**
 * 获取SQL执行日志
 * @param string $out
 * @return NULL|number[]|array[]|string[]
 * author shenxin
 */
function get_query_log($out = false,$querys = array(),$append_color = true,$replace = false){
    $querys = empty($querys)?get_query_log_array():$querys;
    if(empty($querys) || !is_array($querys)){
        return null;
    }
    $result_query_format = array();
    if($append_color){
        $red_string = '<samp style="color:red">%s</samp>';
        $blue_string = '<samp style="color:blue;">%s</samp>';
    }else{
        $red_string = '%s';
        $blue_string = '%s';
    }
    $max_exclude = 2;
    $total_query_time = array();
    $all_group_times = array();
    $querys_temp = array();
    $querys_temp_g = array();
    $total_time_c = array();
    foreach ($querys as $q){
        $query_times = $q['time'];
        $query_ok = $q['flag'];
        $total_query_time[] = $query_times;
        $color_string = sprintf($blue_string,$query_times);
        if($query_times>=$max_exclude || $query_ok!='ok'){
            $color_string = sprintf($red_string,$query_times);
        }
        $error_message = $q['error_message'];
        $waring = $q['waring'];
        $query = $replace?CLib::replace_space($q['query']):$q['query'];
        if($waring){
            $query_new = sprintf($red_string,$query.';'.$waring);
        }
        if($query_ok!='ok'){
            $query_new = '[ERROR]'.$query.';->'.$error_message;
            $query_new =sprintf($red_string,$query_new);
        }else{
            $query_new =$query;
        }
        $total_time_c[$q['_key_']][] = $q['time'];
        $querys_temp_g[$q['_key_']][$q['key']] = str_replace(array( 
            '<server_info>',
            '<server_run_model>',
            '<query_log>',
            '<run_date>',
        ),array( 
            $color_string,
            $q['server'],
            $query_new,
            ''
           // date("dHis",$q['mt'])
        ),'[<server_run_model> <server_info>]<query_log>;');
    }
    foreach ($querys_temp_g as $qkey=>$query){
        $used = array_sum($total_time_c[$qkey]);
        $total = count($query);
        $new_key = $qkey.' totalUsed->'.$used.' QueryNum->'.$total;
        $result_query_format[$new_key] = $query;
    }
    $querys_temp_g=$total_time_c = $querys = null;
    $querys = $temp = $query = null;
    return array(
        'total_used'=>array_sum($total_query_time),
        'querys'=>$result_query_format,
    );
}