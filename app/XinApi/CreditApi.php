<?php

namespace App\XinApi;

use App\Library\Helper;
use App\Library\HttpRequest;

/**
 * credit.youxinjinrong.com的接口类
 */
class CreditApi
{
    const CREDIT_KEY_SECRET = 'jsie28*7$2d*^%$Ji3#';
    const CREDIT_KEY_SECRET_TEST = 'm&8*^%$Ji3#';

    /**
     * 根据用户名，身份证号获取征信变量
     * wiki:http://doc.xin.com/pages/viewpage.action?pageId=6982080
     */
    //const GET_CREDIT_DETAIL_URL_TEST = 'http://new_source.credit.ceshi.youxinjinrong.com/getCreditDetail';
    const GET_CREDIT_DETAIL_URL_TEST = 'http://develop.credit.ceshi.youxinjinrong.com/getCreditDetail';
    const GET_CREDIT_DETAIL_URL = 'http://credit.youxinjinrong.com/getCreditDetail';
    public static function getCreditDetail($applyId)
    {
        $paramList = ['applyid' => $applyId];
        $secret = Helper::isProduction() ? self::CREDIT_KEY_SECRET : self::CREDIT_KEY_SECRET_TEST;
        $paramList['_apikey'] = self::makeApiKey($paramList, $secret);

        $url = Helper::isProduction() ? self::GET_CREDIT_DETAIL_URL : self::GET_CREDIT_DETAIL_URL_TEST;
        return HttpRequest::getJson($url, $paramList);
    }


    protected static function genSig($paramList, $secret) {
        ksort($paramList);
        $joined = http_build_query($paramList);
        $appended = $joined . $secret;
        $urlDecoded = urldecode($appended);

        return md5($urlDecoded);
    }

    protected static function makeApiKey($paramList, $secret)
    {
        $sign = self::genSig($paramList, $secret);
        $apiKey = '';
        $apiKeySeq = [20, 15, 0, 3, 1, 5];
        foreach ($apiKeySeq as $index) {
            $apiKey .= $sign[$index];
        }

        return $apiKey;
    }

    /**
     * 根据applyid获取征信变量
     * wiki:http://doc.xin.com/pages/viewpage.action?pageId=10526772
     */
    const GET_NEW_CREDIT_DETAIL_URL_TEST = 'http://fix723.credit.ceshi.youxinjinrong.com/api/face_decision_applys';
//    const GET_NEW_CREDIT_DETAIL_URL_TEST = 'http://ms.credit.ceshi.youxinjinrong.com/api/face_decision_apply';
    const GET_NEW_CREDIT_DETAIL_URL = 'http://i.credit.youxinjinrong.com/api/face_decision_applys';
    const GET_NEW_CREDIT_DETAIL_TEST_KEY = 'm&8*^%$Ji3#';
    const GET_NEW_CREDIT_DETAIL_KEY = 'jsie28*7$2d*^%$Ji3#';
    public static function get_new_credit_detail($apply_id)
    {
        if(empty($apply_id)){
            return null;
        }
        $key = Helper::isProduction() ? self::GET_NEW_CREDIT_DETAIL_KEY : self::GET_NEW_CREDIT_DETAIL_TEST_KEY;
        $data = ['applyid'=>$apply_id];
        ksort($data);
        $joined = http_build_query($data);
        $appended = $joined.$key;
        $urlDecoded = urldecode($appended);
        $sign = md5($urlDecoded);
        $apiKey = "";
        $apiKeySeq = [20, 15, 0, 3, 1, 5];
        foreach ($apiKeySeq as $index) {
            $apiKey .= $sign[$index];
        }
        $paramList = ['applyid' => $apply_id, '_apikey' => $apiKey];
        $url = Helper::isProduction() ? self::GET_NEW_CREDIT_DETAIL_URL : self::GET_NEW_CREDIT_DETAIL_URL_TEST;
//        $url='http://fix_20190118.credit.ceshi.youxinjinrong.com/api/face_decision_apply?applyid=1234691995&_apikey=2fc45c';
//        $paramList=[];
        $res =  HttpRequest::getJson($url, $paramList);
//        $res['person_credit_result_history'][0]['direct_allow_back'] =1;
        return $res;
    }

    /**
     *  提供方:刘亚楠
     *  获取捞回方式
     *  $apply_id支持多个 100,200,300
     */
    const GET_BACK_DETAIL_URL_TEST = 'http://develop.credit.ceshi.youxinjinrong.com/api/getCreditStatusFromDirectAllowBack';
    const GET_BACK_DETAIL_URL = 'http://i.credit.youxinjinrong.com/api/getCreditStatusFromDirectAllowBack';
    public static function get_back_detail($apply_ids)
    {
        if(empty($apply_ids)){
            return null;
        }
//        $apply_ids= '1234696217,1234696216,1234696214';
//        $apply_ids= '1234696214';
        $key = Helper::isProduction() ? self::GET_NEW_CREDIT_DETAIL_KEY : self::GET_NEW_CREDIT_DETAIL_TEST_KEY;
        $data = ['apply_ids'=>$apply_ids];
        ksort($data);
        $joined = http_build_query($data);
        $appended = $joined.$key;
        $urlDecoded = urldecode($appended);
        $sign = md5($urlDecoded);
        $apiKey = "";
        $apiKeySeq = [20, 15, 0, 3, 1, 5];
        foreach ($apiKeySeq as $index) {
            $apiKey .= $sign[$index];
        }
        $paramList = ['apply_ids' => $apply_ids, '_apikey' => $apiKey];
        $url = Helper::isProduction() ? self::GET_BACK_DETAIL_URL : self::GET_BACK_DETAIL_URL_TEST;
        $res =  HttpRequest::getJson($url, $paramList);
        return $res;
    }
    /**
     *  关系网2.0 http://doc.xin.com/pages/viewpage.action?pageId=10535575
     */
    const GET_RELATION_DETAIL_TEST = 'http://unet.fat.youxinjinrong.com/contacts/relation_net';
    const GET_RELATION_DETAIL = 'http://unet.youxinjinrong.com/contacts/relation_net';
    const JUMP_FAST_URL_TEST = 'https://templet_new.fast.ceshi.youxinjinrong.com/fast-visa/all_list?uid=%s';
    const JUMP_FAST_URL = 'https://fast.youxinjinrong.com/fast-visa/all_list?uid=%s';
    const JUMP_FAST_DEVELOPMENT_URL = 'http://fast.ceshi.youxinjinrong.com/fast-visa/all_list?uid=%s';
    public static function get_relation_detail($uid)
    {
        if(empty($uid)){
            return null;
        }
        $paramList = [
            'uid' => Helper::isProduction() ? $uid:1590229529,
            '_ts' => time(),
            '_src' => C("common.relation_src"),
            'curl' => Helper::isProduction() ? self::JUMP_FAST_URL : (is_dev_env()? self::JUMP_FAST_DEVELOPMENT_URL : self::JUMP_FAST_URL_TEST)
        ];
        $paramList['token'] = call_user_func_array(function ($params){
            $privateKey = '(^$@%￥&4dfo9l1((%unet';
            //按照key进行asc排序
            ksort($params);
            $str = '';
            foreach ($params as $key => $value) {
                // 将数组转化为字符串
                if (is_array($value)) {
                    $value = json_encode_new($value, true, false);
                }
                $tmpStr = $key . '=' . $value . '&';
                $str .= $tmpStr;
            }
            $token = md5($str . $privateKey);
            return $token;
        },[$paramList]);
        $url = Helper::isProduction() ? self::GET_RELATION_DETAIL : self::GET_RELATION_DETAIL_TEST;
        if (!empty($paramList)) {
            $url = $url . '?' . HttpRequest::httpBuildQuery($paramList);
        }
        return $url;
    }

    /**
     * 查询关系网数量
     */
    const GET_RELATION_COUNT_TEST = 'http://unet.fat.youxinjinrong.com/relation_net/user_count';
    const GET_RELATION_COUNT = 'http://unet.youxinjinrong.com/relation_net/user_count';
    public static function get_relation_count($uid){
        if(empty($uid)){
            return null;
        }
        $paramList = [
            'uid' => Helper::isProduction() ? $uid:1590229529,
            '_ts' => time(),
            '_src' => C("common.relation_src"),
        ];
        $paramList['token'] = call_user_func_array(function ($params){
            $privateKey = '(^$@%￥&4dfo9l1((%unet';
            //按照key进行asc排序
            ksort($params);
            $str = '';
            foreach ($params as $key => $value) {
                // 将数组转化为字符串
                if (is_array($value)) {
                    $value = json_encode_new($value, true, false);
                }
                $tmpStr = $key . '=' . $value . '&';
                $str .= $tmpStr;
            }
            $token = md5($str . $privateKey);
            return $token;
        },[$paramList]);
        $url = Helper::isProduction() ? self::GET_RELATION_COUNT : self::GET_RELATION_COUNT_TEST;
        $res =  HttpRequest::getJson($url, $paramList);
        if(isset($res['data']) && $res['data']['count']>0){
            return intval($res['data']['count']);
        }
        return 0;
    }

}