<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyToken
{


    protected $request;
    protected $env;
    protected $strKey;
    protected $is_test;


    /**
     * Run the request filter.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $data = $request->input('data','');
        $env = isset($_SERVER['SITE_ENV']) && $_SERVER['SITE_ENV'] == 'production';
        if($env == 'production'){
            $this->strKey = 'ssfd#$()%$jkhds';
            $this->is_test = 0;
        } else {
            $this->strKey = 'jkdfsg%$&$bfg#';
            $this->is_test = 1;
        }
        //测试环境，并且带了test参数。则直接跳过签名
        $test = $request->input('test','');
        if($this->is_test && $test == 1){
            if(isset($_REQUEST['data']) && !empty($_REQUEST['data'])){
                $data = json_decode($_REQUEST['data'], true);
                $request->replace((array)$data);
            }
            return $next($request);
        }
        //认证
        $params = json_decode($data, true);
        $token = isset($params['token'])?$params['token']:'';
        $resData = [
            'code' => -1,
            'msg' => '签名错误',
            'data' => []
        ];
        if(!$token){
            return response()->json($resData);
        }
        unset($params['token']);
        $t_params = $params;
        ksort($params);
        $sys_token = md5(urldecode(http_build_query($params, '', '&',5) ). $this->strKey);
        if($sys_token != $token){
            return response()->json($resData);
        }
        $data = json_decode($_REQUEST['data'], true);
        $request->replace((array)$data);
        return $next($request);
    }



}
