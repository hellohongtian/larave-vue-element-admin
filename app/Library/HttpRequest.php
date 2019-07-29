<?php
namespace App\Library;

use App\Repositories\Async\AsyncInsertRepository;
use GuzzleHttp;
use Uxin\Finance\NetContainer\NetContainer;

class HttpRequest {

    public static function getJson($url, $paramList = [], $json = true)
    {
        if (!empty($paramList)) {
            $url = $url . '?' . self::httpBuildQuery($paramList);
        }
//        $header = [
//            'headers' => [
//                'Referer' => 'http://fast.youxinjinrong.com',
//            ]
//        ];
        $res = self::sendRequest('GET', $url);
        if ($json) {
            $res = json_decode($res, true);
        }

        return $res;
    }

    /**
     * post方式，json的数据交互
     */
    public static function postJson($url, $paramList = [])
    {
        $res = self::sendRequest('POST', $url, ['form_params' => $paramList]);

        return json_decode($res, true);
    }

    public static function doParamPostJson($url, $paramList = [])
    {
        $res = self::sendRequest('POST', $url, $paramList);
        return json_decode($res, true);
    }

    public static function httpBuildQuery($param)
    {
        if (empty($param)) return '';
        //第三方接口可能不识别http_build_query编码过的query
        $str = "";
        foreach ($param as $k => $v) {
            $str .= $k ."=" .$v ."&";
        }
        $str = rtrim($str, "&");
        return $str;
    }

    protected static function sendRequest($method, $url, $options = [])
    {
        $client = new GuzzleHttp\Client();
        $startTime = microtime(true);
        if ($method == 'GET') {
            $response = $client->request($method, $url);
        } else {
            $response = $client->request('POST', $url, $options);
        }

        $httpCode = $response->getStatusCode();
        if ($httpCode != 200) {
            NetContainer::httpSaveQueryLog($url,$method,$options,$startTime,'httpcode异常:'.$httpCode);
            throw new \Exception('http get fail |' .$url .'|'. json_encode($options));
        }
        $res =  $response->getBody()->getContents();

        $endTime = microtime(true);
        NetContainer::httpSaveQueryLog($url,$method,$options,$startTime,true);

        self::doRemoteRequestLog($method, $url, $res, $startTime, $endTime, $httpCode, $options);
        return $res;
    }

    protected static function doRemoteRequestLog($method, $url, $res, $startTime, $endTime, $httpCode, $options)
    {
        $logUriInfo = parse_url($url);
        $uri = $logUriInfo['host'].$logUriInfo['path'];

        if($method == 'GET'){
            $options = isset($logUriInfo['query']) ? $logUriInfo['query'] : '';
        }
        $log = array(
            'uri' => $uri,
            'http_code' => $httpCode,
            'http_method' => $method,
            'request' => json_encode($options),
            'body' => $res,
            'stime' => $startTime,
            'etime' => $endTime,
            'created_at' => date('Y-m-d H:i:s'),
        );
        (new AsyncInsertRepository())->pushRemoteRequestLog($log);
    }


}