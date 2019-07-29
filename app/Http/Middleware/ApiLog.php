<?php
/**
 * Created by PhpStorm.
 * User: tanrenzong
 * Date: 18/1/31
 * Time: 下午2:23
 */
namespace App\Http\Middleware;

use Closure;

use App\Library\RedisCommon;
use App\Models\VideoVisa\LogRequestFromErp;
use App\Models\VideoVisa\LogRequestFromChaojibao;
use Illuminate\Http\JsonResponse;
class ApiLog{
    /**
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next)
    {
        $response =  $next($request);
        $this->logToQueue($request,$response); //log

        return $response;
    }


    private function logToQueue($request, $response)
    {
        $log = [];
        $method = is_array($request) ? $request[0] : $request->getMethod();
        $uri = is_array($request) ? $request[1] : $request->getUri();
        $options = json_encode($request->all());

        $uri = parse_url($uri)['path'];
        $path_slices = explode('/', strtolower($uri));

        if($path_slices[1] == 'api' && $path_slices[2] == 'initial-face') { //金融面签接口


            $redisQueue = LogRequestFromErp::LOG_QUEUE;
            $applyId = isset($request->applyid) ? $request->applyid : -1;
        }elseif($path_slices[1] == 'api' && $path_slices[2] == 'visa') { //超级宝接口
            $redisQueue = LogRequestFromChaojibao::LOG_QUEUE;
            $masterId = isset($request->masterid) ? $request->masterid : -1;
        }else { //其他暂不加log
            return false;
        }

        $body = $response->getContent();
        $stime = microtime();//todo
        $etime = microtime();

        $log = array(
            'uri' => $uri,
            'request' => $options,
            'body' => $body,
            'stime' => $stime,
            'etime' => $etime,
            'extra' => '',
            'created_at' => date("Y-m-d H:i:s")
        );
        if(!empty($applyId)) {
            $log['apply_id'] = $applyId;
        }
        if(!empty($masterId)) {
            $log['master_id'] = $masterId;
        }

        $redisObj = new RedisCommon();
        $redisObj->qset($redisQueue, $log);
    }


}