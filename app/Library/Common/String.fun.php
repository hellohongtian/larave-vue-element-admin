<?php
/**
 * @description 
 * @file  String.fun.php
 * @date 下午2:05:36
 * @author shenxin@xin.com
 */

function get_agent(){
    return $_SERVER['HTTP_USER_AGENT'];
}
function get_all_headers($header_key  = ''){
    // 忽略获取的header数据
    $ignore = array('host','accept','content-length','content-type');
    $headers = array();
    foreach($_SERVER as $key=>$value){
        if(substr($key, 0, 5)==='HTTP_'){
            $key = substr($key, 5);
            $key = str_replace('_', ' ', $key);
            $key = str_replace(' ', '-', $key);
            $key = strtolower($key);
            if(!in_array($key, $ignore)){
                $headers[$key] = $value;
            }
        }
    }
    return empty($header_key)?$headers:find_value($header_key, $headers);
}

/**
 * @description
 * @file  V.fun.php
 * @date 下午2:05:58
 * @author shenxin@xin.com
 */
function is_ie6(){
    return @strpos($_SERVER[HTTP_USER_AGENT], "MSIE 6.0")?true:false;
}
function is_ie7(){
    return @strpos($_SERVER[HTTP_USER_AGENT], "MSIE 7.0")?true:false;
}
/**
 * 判断是否是邮箱
 * @param string $str
 */
function is_email($str)
{
    return @preg_match("/^\\w+([-+.]\\w+)*@\\w+([-.]\\w+)*\\.\\w+([-.]\\w+)*$/", $str);
}
/**
 * 检验qq是否合法
 * @param    string      $str       待检验的qq字符串
 * @return   boolean     true是合法的qq,false为非法的qq
 */
function is_qq($str)
{
    return @preg_match("/^[1-9]\\d{4,9}$/", $str);
}
function is_ssl(){
    if(!isset($_SERVER['HTTPS']))return false;
    if($_SERVER['HTTPS'] === 1){  //Apache
        return true;
    }elseif($_SERVER['HTTPS'] === 'on'){ //IIS
        return true;
    }elseif($_SERVER['SERVER_PORT'] == 443){ //其他
        return true;
    }
    return false;
}
/**
 * 检验url是否合法
 * @param    string      $str       待检验的url字符串
 * @return   boolean     true是合法的email,false为非法的url
 */
function is_url($str)
{
    return @preg_match("/^(http|https):\\/\\/[A-Za-z0-9]+\\.[A-Za-z0-9]+[\\/=\\?%\\-&_~`@[\\]\\':+!]*([^<>\"])*$/", $str);
}
/**
 * 判断是否为整型
 */
function check_is_int( $value ) {
    return is_numeric($value) && is_int($value+0);
}

/**
 * 判断是否为浮点数
 */
function check_is_float( $value ){
    return is_numeric($value) && preg_match('/^[0-9]+(\.[0-9]+)$/', $value);
}
/**
 * 判断是否是手机号 （中国大陆）
 * @param int $mobile
 */
function is_mobile($mobile){
    if(empty($mobile))return false;
    return is_numeric($mobile) && strlen($mobile)=='11' && preg_match("/1[34578]{1}\d{9}$/",$mobile);
}
/**
 * 判断是否是post
 */
function is_post()
{
    return $_SERVER['REQUEST_METHOD'] == 'POST' ? true : false;
}
/**
 * 判断是否是get
 */
function is_get()
{
    return $_SERVER['REQUEST_METHOD'] == 'GET' ? true : false;
}

/**
 * 判断是否是AJAX
 */
function is_ajax_call()
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || $_REQUEST['__extend_ajax_form__']=='true' ? true : false;
}
/**
 * 验证身份证号
 */
function is_id_card($idcard_no){
    if (!preg_match('/^[1-9][0-9]{16}[0-9X]$/is', $idcard_no)) {
        return false;
    }
    $idcard_no = strtolower($idcard_no);
    $sum = 0;
    $rem = array(1, 0, 'x', 9, 8, 7, 6, 5, 4, 3, 2);
    $mul = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
    foreach ($mul as $key => $num) {
        if ($idcard_no[$key] == 'x') {
            $sum += 10 * $num;
        } else {
            $sum += $idcard_no[$key] * $num;
        }
    }
    return $idcard_no[17] == $rem[$sum % 11];
}
/**
 * 随机获得字符串
 * @param int $length
 * @param string $type
 * @return string
 */
function rand_string($length,$type = 'ALL') {
    $hash = '';
    switch ($type){
        case 'max':
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            break;
        case 'num':
            $chars = '0123456789';
            break;
        case 'small':
            $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
            break;
        default:
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
    }
    $max = strlen($chars) - 1;
    for($i = 0; $i < $length; $i++){
        $hash .= $chars[mt_rand(0, $max)];
    }
    return $hash;
}
function format_money($price,$ext = '元'){
	return '￥'.split_number($price,$ext);
}
function split_number($repay_interest){
	return substr(sprintf('%.8f', $repay_interest), 0, -6);
}
function json_encode_new($message,$uniq = true,$pretty_print = true){
    $str = array();
    if($uniq && !$pretty_print){
        return json_encode($message,JSON_UNESCAPED_UNICODE);
    }
    if(!$uniq && $pretty_print){
        return json_encode($message,JSON_PRETTY_PRINT);
    }else{
        return json_encode($message,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
/**
 * 替换空白
 * @param string $data
 * @author shenxin@adyimi.com
 * @date 2012-7-4
 */
function replace_space($data) {
	 $a = array(
	 "/\\s(?=\\s)/i",
	 "/[\n\r\t]/",
	 );
	 $b = array(
	 '',
	 '',
	 );
	 return preg_replace($a, $b, $data);
 }
/**
 * 执行PHP
 * @param $content
 *
 */
function _eval($content){
    return eval('?>' . trim($content));
}

/**
 * <code>
 * $data = array('name'=>'test','ary'=>array('test'=>'test key'));
 * _find_value($data,'name.ary.test'); 返回 test key
 * </code>
 * 通过字符串查找数据里的数据
 * @param array $data
 * @param string $arg
 */
function find_value($data, $arg)
{
    if (empty($arg)){
        return $data;
    }   
    $GLOBALS['__temp_val_data__'] = $data;
    $call  = explode('.', $arg);
    $string = '';
    foreach ($call as $item){
        $string .= "['" . trim($item) . "']";
    }
    $code = '<?php return isset($GLOBALS[\'__temp_val_data__\']' . $string . ')?$GLOBALS[\'__temp_val_data__\']' . $string . ':null; ?>';
    $res  = _eval($code);
    $GLOBALS["__temp_val_data__"] = array();
    return $res;
}

/**
 * 判断是否是JSON
 * @param string $string
 * @return boolean
 */
function is_json($string) {
    if((empty($string) || !is_string($string)) || is_array($string))return false;
    if(!(strstr($string, '{') || strstr($string, '[')))return false;
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}
function array_get_val($data,$arg,$default = null){
    if(empty($data) || empty($arg) || !is_array($data))return $default;
    $find = find_value($data, $arg);
    return !$find?$default:$find;
}