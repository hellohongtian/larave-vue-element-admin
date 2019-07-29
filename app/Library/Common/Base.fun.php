<?php
use App\Library\RedisCommon;
use Uxin\Finance\Output\Output;
/**
 * @description 
 * @file  base.fun.php
 * @date 下午6:49:37
 * @author shenxin@xin.com
 */
function is_cli(){
    return PHP_SAPI=='cli'?true:false;
}
function get_url($http = true){
	if(is_cli())return '';
    $string = $_SERVER['REQUEST_URI'];
    $string = empty($string) ? $_SERVER['QUERY_STRING'] : $string;
    $url    = $_SERVER['HTTP_HOST'] . '/' . $string;
    $is_https = strtolower((isset($_SERVER['HTTPS'])?$_SERVER['HTTPS']:''))=='on'?true:false;
    $str = $is_https?'https://':'http://';
    return ($http ? $str : '') . str_replace('//', '/', $url);
}
/**
 * html编码
 * @param unknown_type $array
 * @return return_type
 * @author shenxin
 * @date 2012-6-18下午03:41:39
 * @version V1R6B005
 */
function html_encode($array)
{
    if (empty($array))
        return $array;
        return is_array($array) ? array_map('html_encode', $array) : addslashes(stripslashes(str_replace(array(
            '\&quot',
            '\&#039;',
            '\\'
        ), array(
            '&quot',
            '&#039;',
            ''
        ), trim(htmlspecialchars($array, ENT_QUOTES)))));
}

/**
 * html解码
 * @param array $array
 * @return return_type
 * @author shenxin
 * @date 2012-6-18下午03:41:26
 * @version V1R6B005
 */
function html_decode($array)
{
    if (empty($array))
        return $array;
        return is_array($array) ? array_map("html_decode", $array) : htmlspecialchars_decode($array, ENT_QUOTES);
}
if(!function_exists('send_http_status')){
    /**
     * 发送HTTP状态
     * @param integer $code 状态码
     * @return void
     */
    function send_http_status($code = '') {
        static $_status = array(
            // Informational 1xx
            100 => 'Continue',
            101 => 'Switching Protocols',
            // Success 2xx
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            // Redirection 3xx
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Moved Temporarily ', // 1.1
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            // 306 is deprecated but reserved
            307 => 'Temporary Redirect',
            // Client Error 4xx
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            // Server Error 5xx
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            509 => 'Bandwidth Limit Exceeded'
        );
        if(isset($_status[$code])) {
            header('HTTP/1.1 '.$code.' '.$_status[$code]);
            // 确保FastCGI模式下正常
            header('Status:'.$code.' '.$_status[$code]);
        }
    }
}
/**
 * 获得唯一hash值
 */
function get_uique_id()
{
    return md5(uniqid(microtime(true)));
}
/**
 * 把int转换成kb或mb等
 * @param int $bytes
 * @return string
 */
function format_bytes($bytes, $showBt = true,$space = true){
    $display = array(
        'Byte',
        'KB',
        'MB',
        'GB',
        'TB',
        'PB',
        'EB',
        'ZB',
        'YB'
    );
    $level   = 0;
    while ($bytes > 1024) {
        $bytes /= 1024;
        $level++;
    }
    return round($bytes, 2) . ($space?' ':'') . ($showBt ? $display[$level] : '');
}

/**
 * 转换为INT 默认取整数
 * @param $string
 * @param $ceil
 * @author shenxin@adyimi.com
 * @date 2012-7-4*
 */
function to_int($string, $ceil = true)
{
	$string = trim($string);
    if (empty($string)){
    	return 0;
    }
    return (int)($ceil ? ceil(abs(intval($string))) : abs(intval($string)));
}

/**
 * @param array $array
 * @param object $class
 * @author shenxin@adyimi.com
 * @date 2012-7-4
 */
function array_to_object($array)
{
    return json_decode(json_encode($array));
}
/**
 * 转换为浮点
 * @param $string
 * @author shenxin@adyimi.com
 * @date 2012-7-4
 */
function float_int($string, $len = 2)
{
    if (!is_numeric($string))return 0.00;
    if (empty($string))return 0.00;
    return round(abs(floatval($string)), $len);
}

/**
 * 序列化数据使用
 * @param string|array $data
 * @return string
 */
function serialize_deep($data)
{
    return addslashes(serialize(stripslashes_deep($data)));
}
function stripslashes_deep($str)
{
    if (empty($str))
        return $str;
        return is_array($str) ? array_map('stripslashes_deep', $str) : stripslashes($str);
}
/**
 * 递归反序列化数据
 * @param string||array $data
 * @return mixed
 */
function unserialize_deep($data)
{
    if (empty($data))
        return $data;
        return is_array($data) ? array_map("unserialize_deep", $data) : unserialize($data);
}
function O(){
    return Output::init();
}
/**
 * 设置或获取缓存 目前只支持redis
 * @param string $key
 * @param string | function $data 要保存的数据
 * @param string $exp 过期时间
 * @param string $adapter
 */
function S($key,$data = null,$exp= 3600){
    $config = array();
    return RedisCommon::init(false,'',$config)->getAdapter()->S($key,$data,$exp);
    /*
	if (empty($key)) return array();
	static $_cache_temp_data = array();
	$exp = (int)($exp);
	$cache = RedisCommon::init();
	if($exp<=0){
	    return $cache->delete($key);
	}
	if($data==null){
	    $find_data  = isset($_cache_temp_data[$key])? $_cache_temp_data[$key]:null;
		if($find_data)return $find_data;
		//从缓存里拿
		$find_t = $cache->get($key);
		$_cache_temp_data[$key] = $find_t;
		return $find_t;
	}
	//写缓存
    if($data instanceof \Closure){
        $data = call_user_func($data);
    }
	$cache->setex($key, $data, $exp);
	return $data;*/
}
function Store($use_pconnect = false,$group = 'fast_redis_group_default',$config= array()){
    return RedisCommon::init($use_pconnect,$group,$config);
}
/**
 * 获取缓存 或者设置缓存
 * @param unknown $key
 * @param unknown $data
 * @param number $exp
 * @return array|mixed|string|\Closure|unknown|boolean
 * author shenxin
 */
function New_Cache($key,$data = null,$exp = 3600){
    $callback = S($key);
    if(empty($callback) && $key && $data!==null){
        $callback = S($key,$data,$exp);
    }
    return $callback;
}
/**
 * 缓存获取
 * @param string $hash_key
 * @param callable $callback
 * @param number $expired
 * @param callable $afterCacheCallback
 * @return array|unknown|NULL|string|Closure|number|unknown|array|\Closure
 * @author shenxin
 */
function Cache_get($hash_key,callable $callback = null,$expired = 3600,callable $afterCacheCallback = null){
    //$is_dev = is_dev_env();
    $find = S($hash_key);
    if($find)return $find;
    $data = array();
    if($callback instanceof  \Closure){
        $data = call_user_func($callback);
    }else{
        $data  = $callback;
    }
    //特殊情况 若数据为空可能会导致 老数据不会清理掉 还是返回老数据
    if($data!==null){
        S($hash_key,$data,$expired);
    }
    if($afterCacheCallback instanceof \Closure){
        return call_user_func($afterCacheCallback,$data);
    }
    return $data;
}

/**
 *
 * 基于redis锁的锁容器 内部调用不能存在exit动作，可以抛出异常，当抛出异常时自动释放锁，外部调用时请使用try{}catch{} 调用
 * 特别说明：此函数在常驻进程中使用一定要慎用，可能会有内存释放的问题  在多进程模式下和常驻进程下不推荐使用
 * 可在WEB环境和命令行模式下运行
 * @param string $lock_hash
 * @param int $lock_time 锁的时间 单位 分钟
 * @param Closure $callback_function 回调执行
 * @param string $lock_message 当锁定时返回的消息
 * @param string $throw_exception 是否抛异常 true 若出现异常抛异常，false 返回失败
 * @return array|string|mix
 * @date Aug 12, 2018
 * @author shenxin
 */
function lock_container($lock_hash,$lock_time,Closure $callback_function,$lock_message = '执行中，请稍后...', $throw_exception = true,$signal_list = array()){
    #如果不配置走默认的
    $config = array();
    
    return RedisCommon::init(false,'train_lockx_group',$config)
    ->getAdapter()->lock_container($lock_hash,$lock_time,$callback_function,function($title,$exception_info){
        Notify('mail')->set('st lock_container 触发异常',$exception_info)->setFrequency('lock_container_exception',1,20)->send();
    },$lock_message,$throw_exception,$signal_list);
}

/**
 * 锁机制处理 当返回 true时需校验
 * @param string $lock_name 锁名字
 * @param string $remove 是否删除
 * @param number $lock_time 锁定的时间 单位 分钟
 * @return boolean
 * @author shenxin
 */
function lockx($lock_name,$remove = false,$lock_time = 5){
    return RedisCommon::init(false,'fast_lock_group',array())->getAdapter()->lockx($lock_name,$remove,$lock_time);
    /*
   // if(is_dev_env())return false;
    if(empty($lock_name))return false;
    $lock_time = (int)$lock_time;
    $lock_time = empty($lock_time)?5:$lock_time;
    $hash_key = 'fbi_api_lock_'.md5($lock_name);
    if($remove){
        S($hash_key,'1',-100);
        return false;
    }
    $find = S($hash_key);
    if(!empty($find))return true;
    S($hash_key,1,60*$lock_time);
    return false;*/
}
function lock_x($lock_name,$remove = false,$lock_time = 20){
    return lockx($lock_name,$remove,$lock_time);
}

function __load_app_helper(){
    $extend_auto_load_helper_file = APP_LIBRARY_PATH.'Helper/Helpers.php';
    if(is_file($extend_auto_load_helper_file)){
        require_once $extend_auto_load_helper_file;
    }
}