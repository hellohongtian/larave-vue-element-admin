<?php
/**
 * @desc 公共工具类
 *
 * User: wood
 * Date: 17/5/17
 */

namespace App\Library;

use Illuminate\Support\Facades\Mail;
use App\Library\HttpRequest;
use App\Library\RedisObj;

class Common
{
    //获取二手车的产品方案
    public function getAllProductScheme(){
        $businessTypeList = config('dict.new_business_types');
        return $businessTypeList;
    }
    //获取全部的产品方案（二手车和新车），废弃
    public function getAllProductScheme_old(){

        $procode_key = config('common.product_scheme_redis_key');
        $redisCommon = new RedisCommon();
        $cityMemData = $redisCommon->get($procode_key);
        if($cityMemData){
            return $cityMemData;
        }
        //二手车产品方案
        $usedProductScheme = $this->getProductScheme(1);
        //新车产品方案 业务需求：不展示新车
//        $newProductScheme  = $this->getProductScheme(2);
        $newProductScheme = [];
        $allProductScheme  = array_merge($usedProductScheme,$newProductScheme);
        $redisCommon->setex($procode_key, $allProductScheme, 3600 * 24);
        return $allProductScheme;
    }

    //获取某一类型产品方案
    public function getCarTypeProductScheme($channel_type){
        $procode_key = config('common.car_type_product_scheme_redis_key');
        $procode_key = $channel_type . '_' . $procode_key;
        $redisCommon = new RedisCommon();
        $cityMemData = $redisCommon->get($procode_key);
        if($cityMemData){
            return $cityMemData;
        }
        $usedProductScheme = $this->getProductScheme($channel_type);
        $redisCommon->setex($procode_key, $usedProductScheme, 3600 * 24);
        return $usedProductScheme;
    }

    //获取全部的产品方案（1二手车 2新车）
    public function getProductScheme($channel_type = 2){
        $params = [
            'is_all' => 1,
            'channel_type' => $channel_type,
            'offline' => 1
        ];
        $ret = [];
        try{
            if($_SERVER['SITE_ENV'] == 'production') {
                $url = 'http://finance_v5.finance.ceshi.youxinjinrong.com/api/expr/getProductList';
            } else {
                $url = 'http://finance.youxinjinrong.com/api/expr/getProductList';
            }


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // post数据
            curl_setopt($ch, CURLOPT_POST, 1);
            // post的变量
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            $output = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($output, true);
            $data = isset($result['data'])?$result['data']:[];
            if(!$data) {
                return [];
            }
            foreach ($data as $procode){
                if(!empty($procode['product_erp_label'])){
                    $key = $channel_type . '_' . $procode['product_stcode'];
                    $ret[$key] = $procode['product_erp_label'];
                }
            }
        } catch (\Exception $e){
            return [];
        }

        return $ret;
    }

    /*
     * @desc 发送邮件
     * @param $mailTitle string 邮件标题
     * @param $mailContent string 邮件内容
     * @param $mailFrom string 邮件发送方地址
     * @param $mailTo array 邮件目的地址
     *
     */
    public static function sendMail($mailTitle, $mailContent, $mailTo = [], $mailFrom = '',$attachment = array())
    {
        if(empty($attachment)){
            return Notify('mail')->set($mailTitle,$mailContent)->setReciver($mailTo)->send();
        }else{
            return  api_send_mail($mailTo,$mailTitle,$mailContent,array(),$attachment);
        }
       /*
        $mailTitlePre = $_SERVER['SITE_ENV'] == 'testing' ? '【测试环境】中央面签报错 - ' : '【正式环境】中央面签报错 - ';
        $mailTitle = $mailTitlePre . $mailTitle;
        $mailContent = print_r($mailContent,true);
        if (empty($mailTo)) $mailTo = config('mail.developer');
        try {
            Mail::raw($mailContent, function ($message) use ($mailTitle, $mailFrom, $mailTo) {
                if (empty( $mailFrom )) {
                    $mailFrom = config('mail.username');
                }
                $title = $_SERVER['SITE_ENV'] == 'testing' ? '【测试】中央面签报错' : '中央面签报错';
                $message->from($mailFrom, $title);
                foreach ($mailTo as $address) {
                    $message->to($address);
                }
                $message->subject($mailTitle);
            });
        } catch (\Exception $e) {
            echo 'mail is not send out';
        }*/
    }
    /*
     * public static function sendMail($mailTitle, $mailContent, $mailTo = [], $mailFrom = '',$attachment = array())
    {
        $mailTitlePre = $_SERVER['SITE_ENV'] == 'testing' ? '【测试环境】中央面签报错 - ' : '【正式环境】中央面签报错 - ';
        $mailTitle = $mailTitlePre . $mailTitle;
        $mailContent = print_r($mailContent,true);
        if (empty($mailTo)) $mailTo = config('mail.developer');
        try {
            Mail::raw($mailContent, function ($message) use ($mailTitle, $mailFrom, $mailTo) {
                if (empty( $mailFrom )) {
                    $mailFrom = config('mail.username');
                }
                $title = $_SERVER['SITE_ENV'] == 'testing' ? '【测试】中央面签报错' : '中央面签报错';
                $message->from($mailFrom, $title);
                foreach ($mailTo as $address) {
                    $message->to($address);
                }
                $message->subject($mailTitle);
            });
        } catch (\Exception $e) {
            echo 'mail is not send out';
        }
    }*/
/*public static function sendAttchMail($mailTitle,$mailContent,$mailTo,$mailFrom,$attachment){
        if (empty($mailFrom))  $mailFrom = config('mail.username');
        if (empty($mailTo)) $mailTo = config('mail.developer');

        Mail::raw($mailContent, function ($message) use ($mailTitle, $mailFrom, $mailTo,$attachment) {
            $title = $_SERVER['SITE_ENV'] == 'testing' ? '【测试】中央面签报错' : '中央面签报错';
            $message->from($mailFrom, $title);
            $arr_to = explode(',',$mailTo);
            foreach ($arr_to as $v) {
                if (!empty($v)) {
                    $message->to($v);
                }
            }
            $message->attach($attachment);
            $message->subject($title.$mailTitle);
        });
        unlink($attachment);

    }*/

    //将数组按照某字段作为下标
    public function formatArr($arr, $index){
        if(!$arr || !is_array($arr)){
            return [];
        }
        $ret = [];
        foreach ($arr as $key => $val){
            if(isset($val[$index]) && !empty($val[$index])){
                $ret[$val[$index]] = $val;
            }
        }
        return $ret;
    }

    //获取随机字符串
    public function rand_str($length=8)
    {
        $chars = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'
        ];

        $count = count($chars);
        $str = '';

        //计算长度
        $len = 0;
        $need_len = $length <= $count ? $length : $count;
        while ($len < $length){

            // 在 $chars 中随机取 $need_len 个数组元素键名
            shuffle($chars);
            for ($i = 0; $i < $need_len; $i++) {
                // 将 $need_len 个数组元素连接成字符串
                $str .= $chars[$i];
            }
            $diff_count = $length-$need_len;
            $need_len = $diff_count<=$count?$diff_count:$count;
            $len += $need_len;
        }
        return $str;
    }

    //获取客户端IP地址
    public static function getClientIp() {
        $ip = '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipArr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            if (isset($ipArr[0])) {
                $ip = $ipArr[0];
            }
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_CLIENTIP'])) {
            $ip = $_SERVER['HTTP_CLIENTIP'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $pos = strpos($ip, '|');
        if ($pos) {
            $ip = substr($ip, 0, $pos);
        }
        $ip = trim($ip);
        return $ip;
    }

    public static function create_sn($data, $secret = '', $undate = false) {
    
        $debug = isset($data['debug']) ? $data['debug'] : false;
        unset($data['sn']);
        $str = '';
        ksort($data);
        foreach ($data as $key => $value) {
            $str .="&$key=$value";
        }
        $str = trim($str, "&") . $secret;
        if (!$undate) {
            $str .= date('Y-m-d');
        }
        if ($debug && $debug == $secret) {
            echo "md5 str:", $str;
            echo '<br>';
        }
        return strtolower(md5($str));
    }

    public static function isVpnAddr($ip) {

        $appkey = config('common.vpn_app_key');
        $secret = config('common.vpn_secret');
        $url = config('common.vpn_url');
        $isVpn = false;

        $data = array(
            'appkey' => $appkey,
            'ip' => $ip,
        );
        $data['sn'] = self::create_sn($data, $secret);
        $ret = HttpRequest::postJson($url, $data);
        if(!empty($ret) && $ret['status'] == 0 && !empty($ret['data']) 
            && $ret['data']['inner_mesh'] == 'no') {
            $isVpn = true;
        }

        return $isVpn;
    }

    public static function notifyFontNewVisa($seat_id, $message) {

        $options = array(
            'cluster' => 'ap1',
            'encrypted' => false //是否加密，即是否采用https
        );
//        $pusher = new Pusher(
//            'f13c20e30069630ca0ad',
//            '67aa2f1615ce891371b5',
//            '554236',
//            $options
//        );
        $pusher = new Pusher(
            '3879afbe3669598e0852',
            '1a6616e3a2542ff1883d',
            '655085',
            $options
        );

        $data['message'] = $message;
         return $pusher->trigger('my-channel-'.$seat_id, 'my-event', $data,null,true);
    }

    public static function getUserRelationCount($uid) {

        $relaCount = 0;
        $relation_url = config('common.relation_count_url');
        $_s = config('common.relation_s');
        $secret = config('common.relation_secret');
        $params = [
            'uid' => $uid,
            '_s' => $_s,
        ];
        $params['sn'] = Common::create_sn($params, $secret, true);
        $ret = HttpRequest::getJson($relation_url, $params);
        if(!empty($ret) && $ret['code'] == 0 && !empty($ret['data'])) {
            $relaCount = $ret['data']['relation_num'];
        }

        return $relaCount;
    }


    /*
     * @desc 将对象转换为数据
     * @param $obj obj
     * @return array
     */
    public static function obj2arr($obj)
    {
        if (is_object($obj)) {
            $obj = (array)$obj;
            $obj = self::obj2arr($obj);
        } elseif(is_array($obj)) {
            foreach ($obj as $key => $value) {
                $obj[$key] = self::obj2arr($value);
            }
        }
        return $obj;
    }

    /**
     * 公共加锁方法
     * @param string $hash_key
     * @param bool $remove
     * @param int $time
     * @param bool $dead_lock 重复执行死锁
     * @return bool true已经锁定 false加锁或去锁成功
     */
    public static function redis_lock($hash_key,$remove = false,$time = 1,$dead_lock=false)
    {
        $obj = RedisObj::instance();
        $time = empty($time)? 1:$time;
//        $hash_key = FastKey::VISA_LOCK.$lock_hash;
        if($remove){
            $obj->expire($hash_key, -100);
            $obj->delete($hash_key);
            return false;
        }
        $increment = 1;
        $find = $obj->incr($hash_key);
        if($dead_lock || $find == $increment){
            $obj->expire($hash_key,$time*60);
        }
        if($find>$increment){
            return true;
        }
        return false;
    }

}