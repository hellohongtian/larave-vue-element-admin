<?php
namespace App\Http\Middleware\Login;

use App\Library\Helper;
use App\Library\RedisCommon;
use App\Models\VideoVisa\SeatManage;
use App\Repositories\UserRepository;
use App\Repositories\Visa\ActionRepository;
use App\XinApi\AptService;
use App\XinApi\CommonApi;
use Closure;
use App\Models\VideoVisa\ErrorCodeLog;
use Mockery\Exception;

/**
 *
 */
class LoginMiddleware {

	/**
	 * 运行请求过滤器。
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next) {
        ini_set('session.cookie_domain', '.youxinjinrong.com');
        session_start();
        $sid = session_id();
        $uinfo = session('uinfo');
        $is_iframe = intval(trim($request->input('is_iframe',0)));
        if($is_iframe){
            try{
                    $uinfo = $this->autoLogin($request);
            }catch (\Exception $e){
                $msg = $e->getMessage();
                return response()->view('errors.error', ['msg' => $msg]);
            }
        } else {
            if (session('uinfo.is_iframe')) {
                session()->forget('uinfo.is_iframe');
            }
        }
        $secure = false;
		if (empty($uinfo)) {
			//打一条测试日志，看看到底只走到了哪一个逻辑中(临时借用记录out的日志系统)
			if(!empty($uinfo)) {
				(new ErrorCodeLog())->runLog('110' , $uinfo);
			}
//			(new ErrorCodeLog())->runLog('111' , $sid);
//			(new ErrorCodeLog())->runLog('112' , UserRepository::getLoginSessionIdOnRedis());
			if(is_production_env() || is_test_env()){
                $secure = true;
            }
			return redirect("/login", 302, [], $secure);
		}
		return $next($request);
	}

    /**
     * 自动登录
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
	private function autoLogin($request){
        $uinfo = session('uinfo');
        $master_name = strval(trim($request->input('master_name','')));

        if (!empty($uinfo) && $uinfo['mastername'] == $master_name) {
           $uinfo['is_iframe'] = 1;
           session(['uinfo' => $uinfo]);
           return session('uinfo');
       }
        $token =  strval(trim($request->input('token','')));

        if(!$token || !$master_name ){
            throw new Exception( "页面走丢了.");
        }
        if($token != AptService::getToken([ 'is_iframe' => 1, 'master_name' => $master_name]) || !$this->verifyReferer()){
            throw new Exception( "页面走丢了.");
        }
        $seat_manager = new SeatManage();
        $seatRes = $seat_manager->getOne(['id','roleid','flag','fullname','master_id','mastername','deptname','email','mobile','work_status'],
            ['mastername' => $master_name,'status' => 1]);
        if(empty($seatRes)){
            throw new Exception("中央面签用户不存在.");
        }
        $uinfo['email'] = $seatRes['email'];
        $uinfo['mastername'] = $seatRes['mastername'];
        $uinfo['fullname'] = $seatRes['fullname'];
        $uinfo['mobile'] = $seatRes['mobile'];
        $uinfo['deptname'] = $seatRes['deptname'];
        $uinfo['seat_id'] = $seatRes['id'];
        $uinfo['master_id'] = $seatRes['master_id'];
        $uinfo['role'] = $seatRes['roleid'];
        $uinfo['flag'] = $seatRes['flag'];
//        $uinfo['action'] = ActionRepository::get_menu_list_new(true);
        $uinfo['is_iframe'] = 1;
        RedisCommon::init()->set(ActionRepository::FAST_USE_MENU_LIST_KEY.$uinfo['seat_id'],$uinfo['action']);
        //初始化登录状态日志(默认是离开)
        if ($seatRes['work_status'] != SeatManage::SEAT_WORK_STATUS_LEAVE) {
            $up = $seat_manager->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_LEAVE, ['id'=>$seatRes['id']]);
            if(!$up){
                return json_encode(['code' => -1, 'msg' => '更新坐席失败']);
            }
        }else{
            $seat_manager->updateBy(['work_status'=>SeatManage::SEAT_WORK_STATUS_LEAVE], ['id'=>$seatRes['id']]);
        }
        session(['uinfo' => $uinfo]);
        UserRepository::setLoginSessionOnRedis();
        //sso登录日志
        CommonApi::ssoLoginLog(CommonApi::SSO_LOG_LOGIN, $uinfo['mastername']);
        return $uinfo;
    }
    /**
     * refer校验
     * @return bool
     */
    public function verifyReferer(){
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }

        if (!preg_match('/.*?youxinjinrong.com/', $_SERVER['HTTP_REFERER'])) {
            return false;
        };

        return true;
    }

}

?>