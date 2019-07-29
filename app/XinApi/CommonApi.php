<?php
/**
 * Created by PhpStorm.
 * User: tanrenzong
 * Date: 18/3/13
 * Time: 下午4:49
 */
namespace App\XinApi;

use App\Library\Common;
use App\Library\Helper;
use App\Library\HttpRequest;

class CommonApi {
    const SSO_LOG_LOGIN = 1;
    const SSO_LOG_LOGOUT = 2;

    const SSO_LOGIN_LOG = 'http://sso.youxinjinrong.com/logs/wsyslog';
    const SSO_LOGIN_LOG_TEST = 'http://sso.ceshi.youxinjinrong.com/logs/wsyslog';
    /**
     * wiki:http://doc.xin.com/pages/viewpage.action?pageId=6993442
     * sso登入登出日志
     * @param $type
     * @param $userName
     * @return mixed
     */
    public static function ssoLoginLog($type,$userName)
    {
        $paramList = [
            'hostname' => $_SERVER['HTTP_HOST'],
            'type' => $type,
            'username' => $userName,
            'ip' => Common::getClientIp(),
        ];

        $url = Helper::isProduction() ? self::SSO_LOGIN_LOG : self::SSO_LOGIN_LOG_TEST;
        $res = HttpRequest::getJson($url, $paramList);
        return $res;
    }

    /**
     * 统一的登录验证服务
     * wiki:http://doc.xin.com/pages/viewpage.action?pageId=1606826
     */
    const XIN_LOGIN_AUTHENTICATION = 'http://service.xin.com/staff/login';
    const XIN_LOGIN_AUTHENTICATION_TEST = 'http://branch_v2.service.ceshi.xin.com/staff/login';
    public static function xinLoginAuthentication($userName, $pwd)
    {
        $params = array(
            'username' => $userName,
            'pwd' => $pwd,
            'src' => $_SERVER['HTTP_HOST'],
            'encrypt' => 0
        );

        //获取sign
        $secret = 'o0mIi39aQLv0zxEgpuIE'; //测试和线上一致
        ksort($params);
        $expect = md5(urldecode(http_build_query($params) . $secret));
        $params['sign'] = $expect;

        //请求
        $url = Helper::isProduction() ? self::XIN_LOGIN_AUTHENTICATION : self::XIN_LOGIN_AUTHENTICATION_TEST;

        $res = HttpRequest::postJson($url, $params);
        return $res;
    }

    /**
     * 黑名单收集接口
     * wiki:http://doc.xin.com/pages/viewpage.action?pageId=12865193
     */
    const OPEN_VEG_ADD_BLACK = 'http://dw.youxinjinrong.com/api/openveg/add_black';
    const OPEN_VEG_ADD_BLACK_TEST = 'http://wq1_openveg.dw.test.youxinjinrong.com/api/openveg/add_black';
    public static function addBlack($params)
    {
        if (!is_array($params)) {
            return false;
        }
        $mToken = function ($data, $secret = '') {
            unset($data['sn']);
            ksort($data);
            $str = '';
            foreach ($data as $key => $value) {
                $str .= "&$key=$value";
            }
            $st = time();
            $str1 = rand(1000, 9999);
            $str2 = dechex($st - $str1);
            $str = trim($str, "&") . $st . $secret;
            $str = strtolower(substr(md5($str), 8, 16));
            return $str1 . $str . $str2;
        };

        $secret = C('@.common.fast_add_black_s');
        $params['_s'] = $secret;
        $params['node_id'] = 9;//中央面签节点
        $params['sn'] = $mToken($params, $secret);
        //请求
        $url = Helper::isProduction() ? self::OPEN_VEG_ADD_BLACK : self::OPEN_VEG_ADD_BLACK_TEST;
        $res = HttpRequest::postJson($url, $params);
        return $res;
    }
}