<?php
/**
 * Created by PhpStorm.
 * User: tanrenzong
 * Date: 18/1/31
 * Time: 下午2:23
 */
namespace App\Http\Middleware;

use App\Library\RedisCommon;
use App\Repositories\UserRepository;
use App\Repositories\Visa\ActionRepository;
use App\Repositories\Visa\RoleRepository;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Auth\User;

class CheckAuth{

    /**
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle1($request, Closure $next)
    {
        $requestPath= $request->path();

        if (!UserRepository::isDeveloper()) {
            if (UserRepository::isGuest()) { //普通用户
                if (in_array($requestPath, $this->guestBanList())) {
                    throw new AuthenticationException('普通用户不允许该操作');
                }
            } elseif (UserRepository::isSeat()) { //座席用户
                if (in_array($requestPath, $this->seatBanList())) {
                    throw new AuthenticationException('座席用户不允许该操作');
                }
            } elseif (UserRepository::isAdmin()) { //管理员
                if (in_array($requestPath, $this->adminBanList())) {
                    throw new AuthenticationException('管理员用户不允许该操作');
                }
            } else { //超级管理员

            }
        }

        return $next($request);
    }
    /**
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next)
    {
        $requestPath= $request->path();
        if(!empty($action_list) && !in_array($requestPath,ActionRepository::get_action_list_for_auth())){
            abort('403');
//            throw new AuthenticationException('不允许该操作!');
        }

        return $next($request);
    }
    /**
     * 坐席禁用请求列表
     * @return array
     */
    private function guestBanList()
    {
        return [
            //接单
            'fast-visa/get_visa_info',
            //管理员增改查
            'admin/index',
            'admin/add',
            'admin/edit_status',
        ];
    }

    private function seatBanList()
    {
        return [
            //管理员增改查
            'admin/index',
            'admin/add',
            'admin/edit_status',
        ];
    }

    private function adminBanList()
    {
        return [
            'admin/index',
            'admin/add',
            'admin/edit_status',
        ];
    }
}