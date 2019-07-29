<?php
/**
 * 超级宝的相关接口
 * Created by PhpStorm.
 * User: tanrenzong
 * Date: 18/1/26
 * Time: 下午6:20
 */
namespace App\XinApi;

use App\Library\Helper;
use App\Library\HttpRequest;

class AptService {
    /**
     * 根据carId获取销售id
     * wiki：http://doc.xin.com/pages/viewpage.action?pageId=6980421
     */
    const GET_DEAL_SALER_URL_TEST = 'http://super412.apt.ceshi.xin.com/gateway/do/10088/clue_deal_saler';
    const GET_DEAL_SALER_URL = 'https://apt.xin.com/gateway/do/10088/clue_deal_saler';
    public static function getDealerIdByCarId($carId)
    {
        $result = [];
        $params['carid'] = $carId;
        $params['token'] = self::getToken($params);
        $url = Helper::isProduction() ? self::GET_DEAL_SALER_URL : self::GET_DEAL_SALER_URL_TEST;
        $response = HttpRequest::postJson($url, $params);

        if ($response['code'] == 2 && isset($response['data']) && isset($response['data']['salerid'])) {
            $result = $response['data'];
        }
        return $result;
    }

    public static function getToken($params)
    {
        unset($params['token']);
        $privateKey = Helper::isProduction() ? '7D34#f6!4uAb' : '7D34#f6!4aAc';
        ksort($params);
        $str = urldecode(http_build_query($params, '', '&', 2)) . $privateKey;
        $str = md5($str);
        return $str;
    }

    /**
     * 根据carId获取销售id ，全国直购
     * wiki：http://doc.xin.com/pages/viewpage.action?pageId=6980421
     */
    const GET_DEAL_SALER_URL_DIRECT_TEST = 'http://x3.zg.test.xin.com/apis/zgorder/getOneInfo';
    const GET_DEAL_SALER_URL_DIRECT = 'https://zg.xin.com/apis/zgorder/getOneInfo';
    public static function getDealerIdByCarIdDIR($carId)
    {
        $result = [];
        $t = time();
        $params['carid'] = $carId;
        $params['orderstatus'] = '106_400';
        $params['sign'] = self::_createSign($params,$t);
        $params['sign_platform'] = 'financeFast';
        $params['sign_timestamp'] = $t;

        $url = Helper::isProduction() ? self::GET_DEAL_SALER_URL_DIRECT : self::GET_DEAL_SALER_URL_DIRECT_TEST;
        $response = HttpRequest::postJson($url, $params);
        if ($response['error_code'] == 200 && isset($response['data']) && isset($response['data']['data'])) {
            $result = $response['data']['data'];
        }
        return $result;
    }

    /**
     * 构造签名
     * @params  array
     * @return string
     */
    private  static function _createSign($params,$t)
    {
        $platform  = 'financeFast';//参考授权平台 标示
        $timestamp = $t; //时间戳
        $privateKey= is_production_env()? 'sWIueV29Pn3G0Ja3':'w3FJwv0tAEJV2REq'; //参考授权平台对应的加密key

        ksort($params); //参数按key升序排序
        $val = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $sign = md5(md5(md5($platform.$privateKey).md5($timestamp)).md5($val)); //签名算法
        return $sign;
    }

    /**
     * 云和-测消费能力水平
     * wiki：http://doc.xin.com/pages/viewpage.action?pageId=10519394
     */
    const GET_CONSUMPTION_POWER_LEVEL_TEST = 'http://third_data.foundation.ceshi.youxinjinrong.com/api/third/query';
    const GET_CONSUMPTION_POWER_LEVEL = 'http://foundation.youxinjinrong.com/api/third/query';
    public static function get_consumption_power_level($applyid)
    {
        $strKey = 'dff59138bc24e46b0be8193161f4a5f62732d8df';
        $cache_key = 'fast_get_consumption_power_level_'.$applyid;
        $params = [
            "account" => 'video_visa',//video_visa
            "time"=>time(),
            "applyid"=>$applyid,
            "third_id"=>180,
        ];
        ksort($params);
        $query = http_build_query($params);
        $sys_token = md5(urldecode(http_build_query($params). $strKey));
        $url = is_production_env()? self::GET_CONSUMPTION_POWER_LEVEL:self::GET_CONSUMPTION_POWER_LEVEL_TEST;
        $query = sprintf("%s?%s&sign=%s",$url,$query,$sys_token);
        $response = Cache_get($cache_key,function() use ($query) {
            $response = HttpRequest::getJson($query);
            if ($response['code'] == 1 && isset($response['data']) && isset($response['data']['data'])) {
                $result = $response['data']['data'];
            }else{
                $result = 0;
            }
            return $result;

        });

        return $response;
    }
}
