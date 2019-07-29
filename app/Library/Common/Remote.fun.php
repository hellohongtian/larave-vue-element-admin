<?php
use function Faker\time;
use Uxin\Finance\RpcClient\RpcClient;
use App\Library\AppError;
use Uxin\Finance\NetContainer\NetContainer;
use Uxin\Finance\CLib\CLib;

/**
 * @description 
 * @file  Remote.fun.php
 * @date 下午1:58:39
 * @author shenxin@xin.com
 */
/**
 * CURL请求封装
 * @param string $url
 * @param array $post_data
 * @param string $method
 * @throws Exception
 * @return string
 */
function curl_call_data($url,$post_data = array(),$method='GET',$debug = false,$header = array()) {
    if(empty($url))return array();
    if($debug){
        _dump(func_get_args());
    }
    $method = empty($method)?'POST':$method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    #302重新定向问题
    curl_setopt($ch,  CURLOPT_FOLLOWLOCATION, 1); // 302 redirect
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, "30"); //设置超时时间
    curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:44.0) Gecko/20100101 Firefox/44.0');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //设置是将获取的数据返回而不输出
    if($method=='POST' && !empty($post_data)){
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query((array)$post_data));
    }
    $data = curl_exec($ch);
    $error = curl_errno($ch);
    if ($error) {
        $error= 'call api error method->'.__METHOD__.' info->'.print_r(curl_getinfo($ch),true).' curl error:'.print_r($error,true);
        $info = print_r($error,true);
        throw new Exception($info);
    }
    curl_close($ch);
    return trim($data);
}
function header_location($url,$focus_r = true){
	if($focus_r){
		@header('HTTP/1.1 301 Moved Permanently');//发出301头部
	}
	@header("Location:$url");
	exit();
}
function js_location($url){
	$str = '<script type="text/javascript">
	window.onload = function(){
		window.setTimeout(function(){
			window.location.href="%s";
		},100);
	};
	</script>';
	echo sprintf($str, $url);
	exit();
}
function curl_get_contents($path){
    return curl_call_data($path,'GET');
}
function curl_post_data($url,$post_data = array(),$debug = false){
    return curl_call_data($url,$post_data,'POST',$debug);
}
/**
 * 下载数据
 * @param string|stream|etc $file_data 需要保存的文件
 * @param string $file_name 需要保存的文件名
 * @author shenxin@adyimi.com
 * @date 2012-7-4
 */
function download($file_data, $file_name)
{
    @header('Content-type: application/octet-stream');
    @header('Accept-Ranges: bytes');
    $ua = $_SERVER["HTTP_USER_AGENT"];
    if (preg_match("/MSIE/", $ua)) {
        $encoded_filename = urlencode($file_name);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
    } else if (preg_match("/Firefox/", $ua)) {
        header('Content-Disposition: attachment; filename*="utf8\'\'' . $file_name . '"');
    } else {
        @header('Content-Disposition: attachment;filename="' . $file_name . '";');
    }
    @header('Accept-Length: ' . strlen($file_data));
    @header("Content-Transfer-Encoding: binary");
    header('Pragma: cache');
    header('Cache-Control: public, must-revalidate, max-age=0');
    @set_time_limit(0);
    echo $file_data;
    exit();
}

/**
 *  * 下载文件
 * @param $file
 * @author shenxin@adyimi.com
 * @date 2012-7-4
 */
function download_file($file, $file_name = '')
{
    @header('Content-type: application/octet-stream');
    @header('Accept-Ranges: bytes');
    $ua        = $_SERVER["HTTP_USER_AGENT"];
    $file_name = empty($file_name) ? basename($file) : $file_name;
    if (preg_match("/MSIE/", $ua)) {
        $encoded_filename = urlencode($file_name);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
    } else if (preg_match("/Firefox/", $ua)) {
        header('Content-Disposition: attachment; filename*="utf8\'\'' . $file_name . '"');
    } else {
        @header('Content-Disposition: attachment;filename="' . $file_name . '";');
    }
    @header("Content-Transfer-Encoding: binary");
    @header("Content-Length: " . @filesize($file));
    header('Pragma: cache');
    header('Cache-Control: public, must-revalidate, max-age=0');
    @set_time_limit(0);
    @readfile($file);
}

/**
 * 获取当前URL路径
 * @author shenxin@adyimi.com
 * @date 2012-7-4
 */
function get_curent_link($http = true)
{
    $port = $_SERVER['SERVER_PORT'];
    $path = $_SERVER['REQUEST_URI'];
    $path = empty($path) ? $_SERVER['QUERY_STRING'] : $path;
    $port = $_SERVER['SERVER_PORT'];
    $url  = $_SERVER['SERVER_NAME'] . ($port == 80 ? '' : $port) . '/' . $path;
    return ($http ? 'http://' : '') . str_replace('//', '/', $url);
}
/**
 * 获取服务器端IP地址
 * @return string
 */
function get_server_ip() {
    if (isset($_SERVER)) {
        if($_SERVER['SERVER_ADDR']) {
            $server_ip = $_SERVER['SERVER_ADDR'];
        } else {
            $server_ip = $_SERVER['LOCAL_ADDR'];
        }
    } else {
        $server_ip = getenv('SERVER_ADDR');
    }
    return $server_ip;
}
function terminal_style($message,$fontColor = '',$bgColor= ''){
    return CLib::terminal_style($message,$fontColor,$bgColor);
}
function get_ip(){
	static $realip = NULL;
	if ($realip !== NULL) return $realip;
	if (isset($_SERVER)) {
		if(isset($_SERVER['HTTP_X_REAL_IP']) && $_SERVER['HTTP_X_REAL_IP']){
			$realip = $_SERVER['HTTP_X_REAL_IP'];
		}elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
			$arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			foreach ($arr AS $ip) {
				$ip = trim($ip);
				if ($ip != 'unknown') {
					$realip = $ip;
					break;
				}
			}
		} elseif (isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP']) {
			$realip = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			$realip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
		}
	} else {
		if (getenv('HTTP_X_REAL_IP')){
			$realip = getenv('HTTP_X_REAL_IP');
		}elseif (getenv('HTTP_X_FORWARDED_FOR')) {
			$realip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('HTTP_CLIENT_IP')) {
			$realip = getenv('HTTP_CLIENT_IP');
		} else {
			$realip = getenv('REMOTE_ADDR');
		}
	}
	$onlineip = array();
	preg_match("/[\\d\\.]{7,15}/", $realip, $onlineip);
	return !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';
}
/**
 * 获取域名
 */
function get_domain(){
	$host = $_SERVER['HTTP_HOST'];
	$sname = $_SERVER['SERVER_NAME'];;
	return empty($sname)?$host:$sname;
}
/**
 * 判断是否是内网IP访问  当是内网IP返回 true否则 false
 * @param string $ip
 * @return boolean
 */
function is_private_ip($ip = '') {
    $ip = empty($ip)?get_ip():$ip;
	return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}
function is_ip($ip = ''){
    $ip = empty($ip)?get_ip():$ip;
	return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function get_rpc_config(){
    if(is_production_env()){
        $config_file = APPPATH.'config/production/rpc_client_config.php';
    }elseif(is_test_env()){
        $config_file = APPPATH.'config/testing/rpc_client_config.php';
    }else{
        $config_file = APPPATH.'config/rpc_client_config.php';
    }
    return $config_file;
}
function RpcCall($module,$controller,$method,$args = array(),$request_method = 'POST',$headers = array(),$cookies = array()){
    $config_file = get_rpc_config();
    $client = RpcClient::Init($config_file);
    return $client->set($module,$controller,$request_method)->setHeaders($headers)->setCookies($cookies)->$method($args);
}
function get_rpc_query_log(){
    return  RpcClient::getQueryLogs(true);
}
function get_http_call_logs(){
//   return  AppError::get('register_http_call_trace_log');
    return NetContainer::httpGetQueryLog();
}
function get_rpc_url(){
    $file = get_rpc_config();
    $config_data = include $file;
    return $config_data['rpc_client_config'];
}

/**
 * 获取用户useragent
 */
function get_user_os_info(){
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    if(strpos($user_agent, 'micromessenger'))return 'weixin';
    //android  和IOS 都有微信 访问的情况 肯定对匹配到
    if(strpos($user_agent, 'iphone')||strpos($user_agent, 'ipad') || strpos($user_agent, 'ipod')){
        return 'ios';
    }else if(strpos($user_agent, 'android')){
        return 'android';
    }else{
        return 'other';
    }
}

function get_docx_token($url){
    //if(!is_production_env())return '';
    $url = join('/',array_filter(explode('/',strtolower($url))));
    //config/app_auth.php
    $token =  C('@.app_auth.system_auth_token_check_config.docx_token_key');
    $token .=date("Ymd",\time()); 
    return md5(md5($url).$token);
}