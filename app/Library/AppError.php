<?php
namespace App\Library;
use Uxin\Finance\CarloanStorage\Storage;
use App\Library\Hook\Hook;
use Uxin\Finance\CLib\CLib;

/**
 * @description
 * @file AppError.php
 * @date 2016年8月5日下午7:08:24
 * @author shenxin@xin.com
 */
class  AppError{
    public static $query_slow_limit = 5;
    private static $_error_time = 0;
    private static $_register = array();
    // 超时2秒白名单: 白名单内url, 请求时间大于 2 秒不发送报警邮件
    public static $time_limit_White_List = [
        '/api/xw_ocridcard',
        '/test/test',
        '/fast-visa/get_visa_info'
    ];
   public static function getCallStack($exception){
        if (function_exists('xdebug_get_function_stack')) {
            $stack = [];
            foreach (array_slice(array_reverse(xdebug_get_function_stack()), 2, -1) as $row) {
                $frame = [
                    'file' => $row['file'],
                    'line' => $row['line'],
                    'function' => isset($row['function']) ? $row['function'] : '*unknown*',
                    'args' => $row['params'],
                ];
                if (!empty($row['class'])) {
                    $frame['type'] = isset($row['type']) && $row['type'] === 'dynamic' ? '->' : '::';
                    $frame['class'] = $row['class'];
                }
                $stack[] = $frame;
            }
            $ref = new \ReflectionProperty('Exception', 'trace');
            $ref->setAccessible(true);
            $ref->setValue($exception, $stack);
        }
        return $exception;
    }
    /**
     * 捕获错误信息
     * register_shutdown_function 触发
     */
    public static function catchError(){
        $temp = array(
            'trace'=>building_trace(debug_backtrace()),
        );
       
        $log_file = get_server_log_path().'fast_slow_log_query.log';
       /* //请求和 回吐日志记录
        self::write_user_call_log();*/
        //慢查询日志检测
        self::check_slow_query();
        $error_last = error_get_last();

        //请求时间和内存监控
        self::check_run_time();
        $error = empty($error)?$error_last:$error;

        $error_type = $error['type'];
        $check_error = self::_get_errro_level($error_type);
        
        Hook::call(C('@.hook.system_hook_config.system_after_run_end_monitor'),$temp);
        
        if(!$check_error)return ;
        $trace_object = self::getCallStack(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        $trace = building_trace($trace_object->getTrace());
        $title = '系统致命错误->register_shutdown_function';
        $send_data = array(
            'error_last'=>$error_last,
            'error'=>$error_type,
            'trace'=>$trace,
        );
        if(!is_dev_env()){
            Notify('mail')->set($title, $send_data)->send();
        }
        if(is_production_env()){
            if(is_cli()){
                _dump('---------------------Cli系统异常-------------->'.__FILE__);
                _dump($send_data);
            }
        }else{
            _dump('---------------------系统异常-------------->'.__FILE__);
            _dump($send_data);
        }
    }
    /**
     * 监控页面是否存在慢查询日志
     */
    public static function check_slow_query(){
        $e = '20180408';
        /*
        if(is_cli() && date("Ymd")< $e){
            return ;
        }*/

        //单页面执行超N个sql报警
        $query_run_max_num_limit = 50;
        $sql_query_log = get_query_log_array();
        $slow_limit = self::$query_slow_limit;
        $slow_limit = empty($slow_limit)?2:$slow_limit;
        $temp = array();
        $has_find_slow_query = false;
        $have_error = array();
        if($sql_query_log && is_array($sql_query_log)){
            foreach ($sql_query_log as $query_server=>$query){
                $used = $query['time'];
                if($query['flag']=='error'){
                    $have_error[] = true;
                }
                if($used>$slow_limit){
                    $has_find_slow_query = true;
                    break;
                }
            }
            $ef = array_group_by($sql_query_log, 'name');
            $hash_find_max_sql_group = array();
            foreach ($ef as $ekey_name=>$etotal){
                $group_total = count($etotal);
                if($group_total>$query_run_max_num_limit){
                     $hash_find_max_sql_group[$ekey_name] = 1;
                    break;
                }
            }
            $ef = null;
            if($hash_find_max_sql_group){
                $title_warning = sprintf(' MySQL Query log has exceeded the upper limit %s in this query group, please check whether the program is necessary to adjust.',$query_run_max_num_limit);
                $send_data['waring'] = $title_warning;
                $send_data['detail'] = $hash_find_max_sql_group;
                Notify()->set($title_warning, $send_data)->send('credit_fbi_system_'.$title_warning,500);
            }
            $hash_find_max_sql_group = null;
        }
        if($have_error){
            $parse_model_title = 'mysql执行告警。。。。。。。。。';
            $parse_model_content = sprintf('<samp style="color:red;">存在执行错误的MYSQL，总数%s-详细见debug模式追加的数据！</samp>',count($have_error));
            $have_error = null;
            Notify('mail')->set($parse_model_title,$parse_model_content)->send($parse_model_title,20);
        }
        $sql_query_log = null;
        if($has_find_slow_query){
            $parse_model_title = '存在慢查询query';
            $parse_model_content = '<samp style="color:red;">存在慢查询query-详细见debug模式追加的数据！</samp>';
            Notify('mail')->set($parse_model_title,$parse_model_content)->send($parse_model_title,20);
        }
    }
    /**
     * 对象数据获取
     * @param string $key
     * @return NULL|mixed
     * author shenxin
     */
    public static function get($key = ''){
        return isset(self::$_register[$key])?self::$_register[$key]:null;
    }
    /**
     * 数据注册
     * @param string $key
     * @param mixed $val
     * @return boolean
     * author shenxin
     */
    public static function register($key,$val = null){
        if($val!==null && !empty($key)){
            self::$_register[$key] = $val;
            return true;
        }
    }
    public static function log_sql_query_logs($data){
        return log_query_logs($data);
    }
    public static function append($key,$val = null){
        if($val!==null && !empty($key)){
            self::$_register[$key][] = $val;
            return true;
        }
    }
    private static function _get_errro_level($error_type){
        //8 notice级别错误就不报了  ,8192
        if(in_array($error_type, array(1,2,4,16,32,64,128,256,512,2048,4096,16384))){
            # self::write_error_log($message, '','error');
            return true;
        }
        return false;
    }
    public static function get_errro_level($error_type){
    	return self::_get_errro_level($error_type);
    }
    /**
     * 判断是否是在白名单内
     * @return boolean
     * @date Mar 13, 2019
     * @author shenxin
     */
    private function _checkWhiteUrl(){
        // 在白名单的路由
        $uri = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH );
        $uri = str_replace('/','',strtolower(trim($uri)));
        $self_replace = array_map(function($val){
            return trim(str_replace('/','',strtolower($val)));
        }, self::$time_limit_White_List);
        // 在白名单的路由不发邮件
        if (in_array($uri, $self_replace)){
            return true;
        }
        return false;
    }
    public static function check_run_time(){
        $is_cli = PHP_SAPI=='cli'? true: false;
        if($is_cli) return;
        $run_time = get_run_time();
        $time_limit = 5;
        $mem_limit = 1024*1024*128;
        $used_m = memory_get_usage(true);
        $title_sends = array();
        if($used_m >$mem_limit){
            $title_sends[] = sprintf('接口内存占用较大,当前占用->%s ,请检查',format_bytes($used_m));
        }
        if($run_time >$time_limit && !$this->_checkWhiteUrl()){
            $title_sends[] = sprintf('接口耗时超过%s 秒,请检查',$time_limit);
        }
        if($title_sends){
            $title = sprintf('接口耗时超过%s 秒,请检查!',$time_limit);
            Notify('mail')->set($title, $title_sends)->send($title,100);
        }
    }
    static function get_title($title,$append_tile=''){
        $title = empty($title)?'金融fast系统-系统异常！':'金融fast系统-'.$title;
        $title = '【'.ENVIRONMENT.'】'.$title;
        if($append_tile)$title .=" - ".$append_tile;
        return $title;
    }
    static function get_sms_wx_title($title,$append_tile=''){
        $title = empty($title)?'金融fast系统-系统异常！':'金融fast系统-'.$title;
        $title = '【'.ENVIRONMENT.'】'.$title;
        if($append_tile)$title .=" - ".$append_tile;
        return $title;
    }
    static function get_debug_trace_info(){
        return  CLib::get_server_debug_info(function(){
            return get_run_time();
        },function(){
            $request = Request()->all();
            $request_string = '';
            if($request){
                $e_request = json_encode($request,256);
                //强行截取一段数据
                $request_string = strlen($e_request) >1024*20?substr($e_request,0,1000):$request;
                $request = null;
            }
            return $request_string;
        },function(){
            return building_trace(debug_backtrace(),true);
        },function(){
            $query_log = get_query_log(false,array(),true,true);
            return array(
                'DB相关日志'=>$query_log,
                'rpc请求日志'=>get_rpc_query_log(),
                'redis日志'=>Storage::debug(false,true,false),
                '其他网络请求日志'=>get_http_call_logs(true),
            );
        });
    }
    static function get_debug_trace_info_old(){
        global $argv;
        $call_url = get_url(true);
        $link = get_curent_link();
        $call_ip = CLib::get_ip();
        $is_cli = CLib::is_cli();
        $http_call_debug =  array();
        $error_last = error_get_last();
        $exception_info = array();
        if($error_last &&  self::_get_errro_level($error_last['type'])){
            $exception_info = array(
                'last_error'=>$error_last,
                '_debug_trace'=>building_trace(debug_backtrace(),true),
            );
        }
        if(!$is_cli){
            $request = Request()->all();
            if($request){
                $request_string = strlen(json_encode($request)) >1024*20?'请求数据包太大了，丢弃不显示':$request;
                $request = null;
            }
            $all_header = CLib::get_all_headers();
            if($all_header){
                foreach (array('user-agent','cookie','accept-language','accept-encoding',) as  $d){
                    unset($all_header[$d]);
                }
            }
            $http_call_debug = array(
                '用户请求URL地址' => $call_url,
                '服务器配置HOST对应地址' => $link,
                'request_url' => isset($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:'',
                'request_method' => isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'',
                'http_referer' => isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:'',
                'server_protocol' => isset($_SERVER['SERVER_PROTOCOL'])?$_SERVER['SERVER_PROTOCOL']:'',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:'',
                'hostname' => empty($_SERVER['HOSTNAME']) ? '' : $_SERVER['HOSTNAME'],
                'server_name' => isset($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:'',
                '用户请求IP' => $call_ip,
                'header_info' => array(
                    '服务器发送'=>headers_list(),
                    '服务器接收'=>$all_header,
                ),
                'request信息'=>$request_string,
                '_request_origin'=>file_get_contents('php://input','r'),
                'cookies' => $_COOKIE,
            );
            $request_string = null;
        }else{
            $http_call_debug = array(
                '命令行请求参数' => $argv,
            );
        }
        $query_log = get_query_log(false,array(),true,true);
        $trace = array(
            '服务器基本信息'=>array(
                'uname'=>php_uname(),
                '服务器时间'=>date('Y-m-d H:i:s', time()),
                '服务器IP'=>CLib::get_server_ip(),
                '脚本请求时间' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
            ),
            '应用情况'=>array(
                '脚本总耗时'=>get_run_time(),
                '内存占用'=>format_bytes(memory_get_usage(true)),
                '内存申请'=>format_bytes(memory_get_peak_usage(true)),
                '是否Cli模式'=>$is_cli?'yes':'no',
            ),
            '用户来路相关信息'=>$http_call_debug,
            '异常信息(若有)'=>$exception_info,
            '日志信息'=>array(
                'DB相关日志'=>$query_log,
                'rpc请求日志'=>get_rpc_query_log(),
                'redis日志'=>Storage::debug(false,true,false),
                '其他网络请求日志'=>get_http_call_logs(true),
            ),
        );
        $query_log = null;
        $http_call_debug  = $exception_info = null;
        return $trace;
    }
    /*static function get_debug_info($content = ''){
        $trace = self::get_debug_trace_info();
        $msg = ''; 
        if($content){
            $msg .= "<fieldset><legend>消息内容</legend><pre style='color:red;'>".print_r($content,true)."</pre></fieldset>";
        } 
        $msg .= sprintf("<br /><br /><fieldset><legend>附加服务器相关信息</legend><pre>%s</pre></fieldset>",print_r($trace,true));
        $trace = null; 
        return $msg;
    } */
    static function get_debug_info($content = ''){
        $trace_data = self::get_debug_trace_info();
        $msg = '';
        if($content){
            $msg .= "<fieldset><legend>消息内容</legend><pre style='color:red;'>".print_r($content,true)."</pre></fieldset>";
        }
        $msg .= sprintf("<br /><br /><fieldset><legend>服务器相关信息</legend><pre>%s</pre></fieldset>",print_r($trace_data,true));
        $trace = null;
        return $msg;
    }
}
