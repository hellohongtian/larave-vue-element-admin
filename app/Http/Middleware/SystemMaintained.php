<?php
namespace App\Http\Middleware;

use App\Library\RedisCommon;
use App\Repositories\UserRepository;
use Closure;
#检查是否处于维护中状态
class SystemMaintained
{
    public function handle($request, Closure $next)
    {
        $request = $next($request);
        $redis_obj = RedisCommon::init();
        $maintained_info = $redis_obj->get(config('common.__maintained__'));
        if( !empty($maintained_info) && !UserRepository::isDeveloper() && !in_array(session('uinfo.mastername'),explode(',',$maintained_info['desc']))){
            return response()->view('errors/600',['end_time'=>$maintained_info['maintained_end']]);
        }
        return $request;
    }
}