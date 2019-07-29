<?php
use App\Library\Log\Log;
use App\Library\Notify as LoanNotify;
use PHPMailer\PHPMailer\PHPMailer;
/**
 * @description 自动加载函数库里的文件
 * @file  Common.php
 * @date 上午11:56:28
 * @author shenxin@xin.com
 */
function mkdirs($path, $mode = 0777){
    return !is_dir($path)?mkdir($path,$mode,true):true;
}
/**
 * 
 * @param string $driver
 * @return \App\Library\Notify
 * @date Feb 15, 2019
 * @author shenxin
 */
function Notify($driver = 'mail'){
    return LoanNotify::init($driver);
}
/**
 *
 * @param string $driver
 * @return \App\Library\Notify
 * @date Feb 15, 2019
 * @author shenxin
 */
function N($driver = 'mail'){
    return LoanNotify::init($driver);
}
/**
 * 设置头部信息
 * @param string $char
 * @param string $type
 * @author shenxin@xin.com
 * @date 2012-7-4
 */
function _set_header($type = 'text/html',$char = '')
{
    $char = empty($char)?'utf-8':$char;
    switch (trim($type)) {
        case 'javascript':
            @header("Content-Type:application/x-javascript;charset=$char");
            break;
        case 'json':
            @header("Content-Type:application/json;charset=$char");
            break;
        case 'xml':
            @header("Content-type: text/xml;charset=$char");
            break;
        case 'swf':
            @header("Content-type: application/x-shockwave-flash;");
            break;
        case 'gif':
            @header("Content-type:image/gif;");
            break;
        case 'jpg':
            @header("Content-type:image/jpg;");
            break;
        case 'png':
            @header("Content-type:image/png;");
            break;
        case 'css':
            @header("Content-type: text/css;charset=$char");
            break;
        default:
            @header("Content-type: text/html;charset=$char");
    }
}
function set_header($type = 'text/html',$char = ''){
    return _set_header($type,$char);
}
/**
 * 调试数据
 * @author shenxin@adyimi.com
 * @date 2012-7-4
 */
function _dump(){
	_set_header();
	echo '<hr  style=" border:none; border-bottom:1px solid #999;" /><pre>';
    foreach (func_get_args() as $item) {
        print_r($item);
        echo '<br />';
    }
    echo '</pre>';
}
function create_file($data, $file, $cover = "wb+"){
	@mkdirs(dirname($file));
	$fileinfo  = new  SplFileObject ( $file,  $cover);
	if ( $fileinfo -> isWritable ()) {
		$fileinfo -> fwrite ($data);
	}else{
		throw new Exception('can not create file->'.$file);
	}
	return !is_file($file)&& file_exists($file) ? false : true;
}
function __parse($file){
    if (!is_file($file))return '';
        $content = php_strip_whitespace($file);
        $content = substr(trim($content), 5);
        if ('?>' == substr($content, -2))
            $content = substr($content, 0, -2);
        return $content;
}
/**
 * 判断是否是生产环境
 * @return boolean
 */
function is_production_env(){
	return _check_env('production');
}
/**
 * 判断是否是开发环境
 * @return boolean
 */
if(!function_exists('is_dev_env')){
    function is_dev_env(){
        return _check_env('development');
    }
}

/**
 * 判断是否是测试环境
 * @return boolean
 */
if(!function_exists('is_test_env')){
    function is_test_env(){
        return _check_env('testing');
    }
}

function _check_env($check_key){
	return get_env_string()==$check_key?true:false;
}
function get_env_string(){
    $env = $_SERVER['SITE_ENV'];
    if(empty($env)){
        throw new Exception('请配置环境变量 SITE_ENV ！');
    }
    return $env;
}
function get_errro_level($error_type){
	//8 notice级别错误就不报了  ,8192
	if(in_array($error_type, array(1,2,4,16,32,64,128,256,512,2048,4096,16384))){
		# self::write_error_log($message, '','error');
		return true;
	}
	return false;
}
/**
 * 发送邮件  直接发送邮件 不走异常邮件系统
 * @param array $reciver
 * @param string $subject
 * @param string $body
 * @param string $add_other_replay
 * @param array $AddAttachment
 * @return boolean
 */
function api_send_mail($reciver = array(), $subject = '', $body = '', $add_other_replay='', $AddAttachment=array()) {
	$body = print_r($body,true);
	$config= _get_mail_config();
    if(empty($reciver)){
        $default_reciver = $config['developer'];
        if(empty($default_reciver)){
            throw new Exception('缺少收件人配置！');
        }
        $reciver = $default_reciver;
    }
    $reciver = !is_array($reciver)?array($reciver):$reciver;
    $mail = new PHPMailer();
    $mail->CharSet = 'UTF-8'; /* 设置邮件编码 */
    $mail_type = strtolower($config["driver"]);
    $from = $config["username"];
    $mail->From = $from;
    #$mail->Sender = $from;
    $from_name = explode('@',$from)[0];
    $mail->FromName = $from_name;
    /* 处理多个收信人 */
    foreach ($reciver as $r) {
        $e = explode('@', $r);
        $mail->AddAddress($r, array_shift($e));
    }
    $mail->WordWrap = 50;
    $mail->Subject = strip_tags($subject);
    $mail->AltBody = ($body); /* 如果邮箱不支持HTML就用他 */
    $mail->IsHTML(true);
    /* 处理回复人 */
    $replay_mail = !empty($add_other_replay) ? $add_other_replay : $from;
    $mail->AddReplyTo($replay_mail, $from_name);
    switch ($mail_type) {
        case 'mail':
            $mail->MsgHTML($body);
            break;
        case 'sendmail':
            $mail->IsSendmail();
            //  $mail->SetFrom($from,$from_name);
            $mail->MsgHTML($body);
            break;
        case 'smtp':
            $mail->IsSMTP();
            $mail->Host = trim($config["host"]);
            $mail->SMTPAuth = true;
            $mail->Port = $config['port'];
            if($mail->Port=='587'){
                $mail->SMTPSecure = 'tls';
            }
            $time_out = isset($config['timeout']) && $config['timeout']?to_int($config['timeout']):10;
            $mail->Timeout = $time_out;
            $mail->Username = trim($config["username"]);
            $mail->Password = trim($config["password"]);
            $mail->MsgHTML($body);
            break;
        default:return false;
    }
    if (!empty($AddAttachment)) {
        foreach ((array)$AddAttachment as $k => $item) {
            $name = pathinfo($item, PATHINFO_BASENAME);
            $name = !is_numeric($k) ? $name : $name;
            $mail->AddAttachment($item, $name);
        }
    }
    return $mail->Send() ? true : false;
}
/**
 * 自动加载指定系统文件
 * @param string $dir
 * @param string $building_file
 * @param string $exclude_file
 * @throws Exception
 */
function __auto_load_fun($dir = '',$building_file = '.fun.php',$exclude_file = ''){
    static $_autoload_file_lists = array();
    if(!function_exists('scandir')){
        throw new Exception('请开启函数 ->scandir');
    }
    //暂时不编译 线上环境会出现问题没办法清缓存
    $dir      = empty($dir)?__DIR__.'/':$dir;
    $dir = rtrim($dir,'/').'/';
    $date = date("Y-m-d H:i:s");
    foreach (scandir($dir) as $new_file){
        if($new_file!='.' && $new_file!='..'){
            if(!empty($exclude_file) && $exclude_file==$new_file)continue;
            $lenth = strlen($building_file);
            if(trim(substr($new_file, -$lenth, $lenth))===$building_file){
                $end_file = $dir.$new_file;
                $md5 = md5($end_file);
                if(isset($_autoload_file_lists[$md5])){
                    continue;
                }
                if(!is_file($end_file)){
                    throw new Exception('file ->'.$end_file.' not exist');
                }
                require $end_file;
                $_autoload_file_lists[$md5] = 1;
            }
        }
    }
}
function R(){
    return app('Illuminate\Http\Request');
}
if (!function_exists('G')) {
    function G($start, $end = '', $dec = 5){
        static $_info = array();
        if (!empty($end)) { // 统计时间
            $_info[$end] = microtime(true);
            return number_format(($_info[$end] - $_info[$start]), $dec);
        } else { // 记录时间
            $_info[$start] = microtime(true);
        }
    }
}
/**
 * 构造trace数据
 * @param unknown $Trace
 * @param string $full_args
 * @return NULL|string
 */
function building_trace($Trace,$full_args = true,$replace_path = false){
    if(empty($Trace) || !is_array($Trace))return null;
    $str = '';
    foreach ($Trace as $k => $v) {
        $ee = '';
        $j  = '';
        $do_class = isset($v['class'])?$v['class']:null;
        if (!empty($v['args'])) {
            $t = '';
            foreach ($v['args'] as &$a) {
                if($full_args){
                    //$j .= $t . str_replace('\/', '/', json_encode_new($a,true,false));
                    $j .= $t . json_encode_new($a,true,false);
                }else{
                    $j .= $t . "'" . $a . "'";
                }
                $t = ',';
            }
        }
        if (!empty($v['class']) && !empty($v['function'])) {
            $ee .= ' ' . $v['class'] . $v['type'] . $v['function'] . '(' . $j . ')' . PHP_EOL;
        } elseif (empty($v['class']) && !empty($v['function'])) {
            $ee = ' ' . $v['function'] . '(' . $j . ')' . PHP_EOL;
        }
        $str .= '#' . $k . ' ' . (isset($v['file'])?$v['file']:'') . '(' . (isset($v['line'])?$v['line']:'') . '):' . $ee;
    }
    $callback = replace_sensitive_words($str);
    if($replace_path){
        return str_replace(WEB_ROOT_PARENT, '', $callback);
    }
    return $callback;
}

/**
 * 替换系统的一些敏感词
 * @param string $str
 * author shenxin
 */
function replace_sensitive_words($str){
    if(empty($str))return $str;
    if(!is_string($str)){
        $str = (string)($str);
    }
    //默认的DB对象里的密码字符替换
    $replaced = preg_replace_callback('/pass\"\:\"([\s\S].*?)\",\"port\"/',function($callback_rep){
        $find_rep = $callback_rep[0];
        $fix_string = $callback_rep[1];
        if($find_rep && $fix_string){
            return str_replace($fix_string, '******', $find_rep);
        }
    },$str);
        //替换后在一些对象里的替换，另外可能出现在自定义配置里有数据库配置无法替换的情况
        $all_dbpass_find = array_unique(_get_all_database_pass(true));  
        $all_dbpass_find = $all_dbpass = null;
        return $all_dbpass?strtr($replaced,$all_dbpass):$replaced;
}
function _get_all_database_pass($flip = false){
    $config = config('database');
    if(empty($config))return array();
    $result = array();
    array_walk_recursive($config, function($val,$key) use(&$result){
        if($key=='password' && $val){
            $result[] = $val;
        }
    });
        $result =  array_unique($result);
        if(!$flip)return $result;
        return array_map(function($val){
            return '*********';
        },array_flip($result));
}
function object_to_array($data){
    return json_decode(json_encode($data),true);
}
function get_run_time($start = ''){
    $start =  empty($start)?$GLOBALS['FBI_APP_START']:$start;
    return number_format((microtime(true) - $start),5,'.','');
}
function get_server_data_path(){
    $set_log_dir = isset($_SERVER['SITE_DATA_DIR']) && !empty($_SERVER['SITE_DATA_DIR'])?rtrim($_SERVER['SITE_DATA_DIR'],'/'):'';
    $set_log_dir =  empty($set_log_dir)?APPPATH.'storage/data/':$set_log_dir;
    return rtrim($set_log_dir,'/').'/';
}
function create_csv_header($header_title){
    if(empty($header_title) || !is_array($header_title))return '';
    $header_title = array_map("_replace_space_for_csv", $header_title);
    //$tt = join("\t,\t",$header_title);
    $tt = join(",",$header_title);
    return mb_convert_encoding($tt,'GBK','UTF-8')."\n";
}
/**
 * 检查一个字符串是不是UTF8编码 数据多了后会很慢的
 *
 * @param string $string
 * @return boolean
 */
function is_utf8($string) {
    $bo =  preg_match('%^(?:
	[\x09\x0A\x0D\x20-\x7E]
	| [\xC2-\xDF][\x80-\xBF]
	| \xE0[\xA0-\xBF][\x80-\xBF]
	| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
	| \xED[\x80-\x9F][\x80-\xBF]
	| \xF0[\x90-\xBF][\x80-\xBF]{2}
	| [\xF1-\xF3][\x80-\xBF]{3}
	| \xF4[\x80-\x8F][\x80-\xBF]{2}
	)*$%xs', $string);
    return $bo=='1'?TRUE:FALSE;
}
/**
 * 替换空白
 * @param string $data
 * @author shenxin@adyimi.com
 * @date 2012-7-4
 */
function _replace_space_for_csv($data) {
    $a = array(
        "/\\s(?=\\s)/i",
        "/[\n\r\t]/",
        '/,/'
    );
    $b = array(
        '',
        '',
        '，'
    );
    return preg_replace($a, $b, $data);
}
//替换空白type=2的
function _replace_space_for_csv_type2($data) {
    $a = array(
        "/\\s(?=\\s)/i",
        "/[\n\r]/",
        '/,/'
    );
    $b = array(
        '',
        '',
        '，'
    );
    return preg_replace($a, $b, $data);
}

/**
 * 生成CSV格式文件
 * @param array $source
 * @param array $title  生成CSV的单独格式
 * @return string
 */
function create_csv($source,$title = array(),$echo = false,$foucus_header = true,$type=1){
    if(empty($source) || !is_array($source))return $source;
    $str = '';
    if($foucus_header){
        if(empty($title)){
            $title = array_keys(current($source));
        }
        $str .= create_csv_header($title);
    }
    if($echo)echo $str;
    foreach ( $source  as $skey=>$sval){
        $ary = array();
        foreach ($sval as $vv){
            if( $type==1 ){
                $ary[] = addslashes(_replace_space_for_csv($vv));
            }else{
                $ary[] = addslashes(_replace_space_for_csv_type2($vv));
            }

        }
        if( $type==1 ){
            $tt = join("\t,\t",$ary);
        }else{
            $tt = join(',',$ary);
        }
        $str .=  mb_convert_encoding($tt,'GBK','UTF-8')."\n";
    }
    $source = $title = null;
    if($echo)echo $str;
    return $str;
}
/**
 * 获取系统缓存配置目录
 */
function get_server_cache_path(){
    $set_log_dir = isset($_SERVER['SITE_CACHE_DIR']) && !empty($_SERVER['SITE_CACHE_DIR'])?rtrim($_SERVER['SITE_CACHE_DIR'],'/'):'';
    $set_log_dir =  empty($set_log_dir)?APPPATH.'storage/cache/':$set_log_dir;
    return rtrim($set_log_dir,'/').'/';
}
function to_zip_file($file,$new_file_zip_name){
    $zip  = new  \ZipArchive();
    if ( $zip -> open ($new_file_zip_name,\ZipArchive :: CREATE) ===  TRUE ) {
        $zip -> addFile ($file ,  pathinfo($file,PATHINFO_BASENAME) );
        $zip -> close ();
        return $new_file_zip_name;
    }
    return false;
}
/**
 * 获取系统日志保存目录
 * @return string
 */
function get_server_log_path(){
    $set_log_dir = isset($_SERVER['SITE_LOG_DIR']) && !empty($_SERVER['SITE_LOG_DIR'])?rtrim($_SERVER['SITE_LOG_DIR'],'/'):'';
    $set_log_dir = empty($set_log_dir)?APPPATH.'storage/log/':$set_log_dir;

    return rtrim($set_log_dir,'/').'/';
}
function __init_system__(){
    /**
     * 定义错误日志
     * @var unknown
     */
    define('APP_RUNTIME_PATH',  get_server_cache_path().'runtime/');
    define('APP_LOG_PATH',  get_server_log_path());

    /**
     * 定义PHP错误信息
     */
    $php_error_log = APP_LOG_PATH.'fast_php_error/';
    !is_dir($php_error_log)?mkdir($php_error_log,0777,true):'';
    $file = $php_error_log.'php_error_log.log';
    define('PHP_ERROR_FILE', $file);
    /* log_errors = On;打开错误日志
     log_erroes_max_len = 1024;设置每个日志项的最大长度
     error_log = /usr/local/error.log ;指定错误日志写入的文件位置 */

    @ini_set('log_errors','on');
    @ini_set('log_errors_max_len ',0);
    @ini_set('error_log',$file);
}
__init_system__();
__auto_load_fun();