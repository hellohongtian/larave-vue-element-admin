<?php
namespace App\Http\Middleware;
use App\Library\AppError;

/**
 * @description 
 * @date Mar 13, 2019
 * @author shenxin
 */
class ResponseCallBack{
    /**
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, \Closure $next)
    {
        $response = $next($request);
        if(is_production_env())return $response;
        $content = $response->getContent();
        $req = $request->all();
        $headers = get_all_headers();
        if( ((isset($req['--trace']) && $req['--trace']) || (isset($headers['--trace']) && $headers['--trace'])) && ((is_private_ip() && is_production_env()) || !is_production_env())){
                $getOriginalContent = $response->getOriginalContent();
                if(is_array($getOriginalContent)){
                    $getOriginalContent['--trace-info'] = AppError::get_debug_trace_info();
                    if(isset($headers['--ptrace']) && $headers['--ptrace']){
                        $getOriginalContent['--trace-info']['_php_trace'] = building_trace(debug_backtrace(),true,true);
                    }
                    $response->setContent($getOriginalContent);
                }
        }
        $log_info = [
            'request' => $req,
            'response' => $content
        ];
        return $response;
    }
}