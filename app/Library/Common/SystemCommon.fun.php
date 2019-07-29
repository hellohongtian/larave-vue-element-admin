<?php
/**
 * @description 系统公用函数
 * @file  SystemCommon.fun.php
 * @date 上午10:01:58
 * @author shenxin@xin.com
 */
function _get_mail_config(){
    return C('@.mail');
}
/**
 * 获取系统全局配置
 * @param string  $find_key 格式 @.email.host 和PHP代码 $config['host']结果一样   @.email 为对应的文件名  @.是修饰符
 * @return array|null|string
 */
function C($find_key){
    static $config_temp = array();
    $config = array();
    $find_data = array_map("trim",explode('.', str_replace('@.', '', $find_key)));
    if(empty($find_data))return null;
    $file_name = array_splice($find_data,0,1)[0];
    $find_args = join('.',array_filter((array)$find_data));
    if(array_key_exists($file_name,$config_temp)){
        return find_value($config_temp[$file_name], $find_args);
    }
    if(!defined('ENVIRONMENT')){
    	if(!isset($_SERVER['SITE_ENV']) || empty($_SERVER['SITE_ENV'])){
    		$_SERVER['SITE_ENV'] = 'production';
    	}
    	define('ENVIRONMENT', $_SERVER['SITE_ENV']);
    }
    //获取生产环境配置
    $file_path = APPPATH.'config/'.ENVIRONMENT.'/'.$file_name.'.php';
    $file_path_dev = APPPATH.'config/'.$file_name.'.php';
    $tempb = array();
    if(file_exists($file_path)){
        $tempb = include $file_path;
    }elseif(file_exists($file_path_dev)){
        $tempb = include $file_path_dev;
    }
    //兼容 $config变量的获取问题 新的模式是直接return 
    $config = empty($config)?$tempb:$config;
    if(empty($config))return null;
    $config_temp[$file_name] = $config;
    return find_value($config, $find_args);
}
function import($string,$ext = '.php'){
	static $files = array();
	$file = APP_LIBRARY_PATH.str_replace('.', '/', str_replace('@.', '', $string)).$ext;
	$filemd5 = md5($file);
	if(isset($files[$filemd5]))return false;
	if(!is_file($file))throw  new Exception('file ->'.$file .' not exist');
	$files[$filemd5] = 1;
	require $file;
}
/**
 * 设置HTTP头部允许缓存
 */
function set_http_no_cache(){
    @header('Expires: 0');
    @header('Last-Modified: '. gmdate('D, d M Y H:i:s') . ' GMT');
    @header('Cache-Control: no-store, no-cahe, must-revalidate');
    //ie专用
    @header('Cache-Control: post-chedk=0, pre-check=0', false);
    //for HTTP/1.0
    @header('Pragma: no-cache');
}