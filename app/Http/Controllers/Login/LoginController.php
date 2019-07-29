<?php
namespace App\Http\Controllers\Login;

use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Library\Helper;
use App\Library\RedisCommon;
use App\Library\RedisObj;
use App\Library\Qrcode;
use App\Models\VideoVisa\Action;
use App\Models\VideoVisa\Admin;
use App\Models\VideoVisa\Role;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\SeatManagerLog;
use App\Models\Xin\ErpMaster;
use App\Repositories\UserRepository;
use App\Repositories\Visa\ActionRepository;
use App\User;
use App\XinApi\CommonApi;
use Illuminate\Http\Request;
use App\Repositories\SeatManageRepository;

/**
 * 用户登录相关
 */
class LoginController extends BaseController {

	//public $erpMasterModel = null;
	public $seatModel = null;
	public $roleModel = null;

	public function __construct() {
	//	$this->erpMasterModel = new ErpMaster();
		$this->seatModel = new SeatManage();
		$this->roleModel = new Role();
	}

	public function Login() {

        session_start();
		$sid = session_id();
		$logoUrl = urlencode(config('common.logo_url'));

		$value = 'http://wx.xin.com/content/wxnotice.html?{"i":33,"t":"'.$sid.'", "m":"'.$logoUrl.'"}';
		$errorCorrectionLevel = 'L';
		$matrixPointSize = 5; 

		ob_start();
		Qrcode::png($value, false, $errorCorrectionLevel, $matrixPointSize, 2);
		$qrcodeImage = base64_encode(ob_get_contents());
		ob_end_clean();

		//是否vpn登录
		$clientIp = Common::getClientIp();
		//$isVpn = !empty($clientIp) ? Common::isVpnAddr($clientIp) : false;
		$isVpn = false;
		return view('login.login', [
			'qrcodeImage' => $qrcodeImage,
			'isVpn' => $isVpn,
		]);
	}

	public function onLogin(Request $request) {

		$params = $request->all();
		$username = $params['username'];
		$password = $params['password'];
		if (empty($username)) {
			return json_encode(['code' => -1, 'msg' => '用户名不能为空']);
		}
		if (empty($password)) {
			return json_encode(['code' => -1, 'msg' => '密码不能为空']);
		}

		if (!Helper::isProduction()) {
			if ($username == 'admin' && $password = 'admin123') {
				$uinfo['email'] = '';
				$uinfo['mastername'] = 'admin';
				$uinfo['fullname'] = '测试管理员';
				$uinfo['mobile'] = '1234565789';
				$uinfo['deptname'] = '测试测试测试';
				$uinfo['seat_id'] = 0;
				$uinfo['master_id'] = 0;
				$uinfo['role'] = UserRepository::ROLE_ADMIN;
				session(['uinfo'=>$uinfo]);
				UserRepository::setLoginSessionOnRedis();
				return json_encode(['code' => 1, 'msg' => 'success']);
			} elseif ($username == 'root' && $password = 'root456') {
				$uinfo['email'] = '';
				$uinfo['mastername'] = 'admin';
				$uinfo['fullname'] = '测试炒鸡管理员';
				$uinfo['mobile'] = '1234565789';
				$uinfo['deptname'] = '质量部';
				$uinfo['seat_id'] = 0;
				$uinfo['master_id'] = 0;
				$uinfo['role'] = UserRepository::ROLE_ROOT;
				session(['uinfo'=>$uinfo]);
				UserRepository::setLoginSessionOnRedis();
				return json_encode(['code' => 1, 'msg' => 'success']);
			}
		} else {
			if ($username == 'fastroot' && $password == 'fast!=1+1@%&QDd') {
                return json_encode(['code' => -1, 'msg' => '用户已被禁用!']);

                $uinfo['email'] = '';
				$uinfo['mastername'] = 'root';
				$uinfo['fullname'] = '超级管理员';
				$uinfo['mobile'] = '1234565789';
				$uinfo['deptname'] = '产品技术体系金融';
				$uinfo['seat_id'] = 0;
				$uinfo['master_id'] = 0;
				$uinfo['role'] = UserRepository::ROLE_ROOT;
				session(['uinfo'=>$uinfo]);
				UserRepository::setLoginSessionOnRedis();
				//sso登录日志
				CommonApi::ssoLoginLog(CommonApi::SSO_LOG_LOGIN, $uinfo['mastername']);
				return json_encode(['code' => 1, 'msg' => 'success']);
			}
		}
		//获取公司用户信息
		$res = CommonApi::xinLoginAuthentication($username, $password);
		if ($res['code'] != 0) {
			return json_encode(['code' => -1, 'msg' => '用户或者密码错误']);
		}
		$uinfo['email'] = $res['data']['email'];
		$uinfo['mastername'] = $res['data']['mastername'];
		$uinfo['fullname'] = $res['data']['fullname'];
		$uinfo['mobile'] = $res['data']['mobile'];
		$uinfo['deptname'] = $res['data']['deptname'];
		$uinfo['seat_id'] = 0;
		$uinfo['master_id'] = 0;
		$uinfo['role'] = UserRepository::ROLE_GUEST;
		$seatInfo = $this->seatModel->select('id','work_status','status','master_id','roleid')->where(['mastername' => $uinfo['mastername'], 'status'=>1])->first();
		if (!empty($seatInfo)) {
			$seatInfo = $seatInfo->toArray();
			if($seatInfo['status'] == 2) {
				return json_encode(['code' => -1, 'msg' => '用户已被禁用!']);
			}
            $role_flag = $this->roleModel->getOne(['roleid','flag'],['roleid'=>$seatInfo['roleid']]);
			$uinfo['role'] = $seatInfo['roleid'];
			$uinfo['flag'] = $role_flag['flag'];
			$uinfo['seat_id'] = $seatInfo['id'];
			$uinfo['master_id'] = $seatInfo['master_id'];
//			$uinfo['action'] = ActionRepository::get_menu_list_new(true);
			RedisCommon::init()->set(ActionRepository::FAST_USE_MENU_LIST_KEY.$uinfo['seat_id'],$uinfo['action']);
            //初始化登录状态日志(默认是离开)
            if ($seatInfo['work_status'] != SeatManage::SEAT_WORK_STATUS_LEAVE) {
                $up = $this->seatModel->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_LEAVE, ['id'=>$seatInfo['id']]);
                if(!$up){
                    return json_encode(['code' => -1, 'msg' => '更新坐席失败']);
                }
            }else{
                $this->seatModel->updateBy(['work_status'=>SeatManage::SEAT_WORK_STATUS_LEAVE], ['id'=>$seatInfo['id']]);
            }
		} else {
		    return json_encode(['code' => -1, 'msg' => '系统用户不存在']);
		}
		session(['uinfo' => $uinfo]);
		UserRepository::setLoginSessionOnRedis();
		//sso登录日志
		CommonApi::ssoLoginLog(CommonApi::SSO_LOG_LOGIN, $uinfo['mastername']);
		return json_encode(['code' => 1, 'msg' => 'success']);
	}

	static public function getLdapUser($mastername, $pwd = '') {
		try {
			$ldap = config('database.ldap');
			// 连上有效的 LDAP 服务器
			$connect = ldap_connect($ldap['host'], $ldap['port']);
			ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
			// 系住 LDAP 目录，返回true/false
			$bind = @ldap_bind($connect, $ldap['user'], $ldap['pass']);
			if ($bind) {
				// 设置搜索条件
				$filter = 'samaccountname=' . $mastername;
				$attributes = array(
					'samaccountname',
					'cn',
					'mobile',
					'mail',
					'department',
				);
				// 根据条件列出树状简表
				$result = ldap_search($connect, $ldap['dn'], $filter, $attributes);
				// 取得全部返回资料
				$info = ldap_get_entries($connect, $result);

				$data = [
					'code' => -1,
					'data' => [],
					'msg' => '抱歉，您不是内部员工！',
				];
				if ($info["count"] != 0) {
					$ret['mastername'] = isset($info[0]['samaccountname'][0]) ? $info[0]['samaccountname'][0] : '';
					$ret['fullname'] = isset($info[0]['cn'][0]) ? $info[0]['cn'][0] : '';
					$ret['mobile'] = isset($info[0]['mobile'][0]) ? $info[0]['mobile'][0] : '';
					$ret['email'] = isset($info[0]['mail'][0]) ? $info[0]['mail'][0] : '';
					$ret['deptname'] = isset($info[0]['department'][0]) ? $info[0]['department'][0] : '';

					// 每个人都会有所谓的显名 (distinguished name, 简称 dn)
					$user_dn = $info[0]["dn"];
					$bind2 = @ldap_bind($connect, $user_dn, $pwd);
					if ($bind2) {
						$data['code'] = 0;
						$data['data'] = $ret;
						$data['msg'] = '成功';
					} else {
						$data['msg'] = '用户名或者密码错误！';
					}
					return $data;
				}
				return $data;
			}
		} catch (\Exception $e) {
			throw $e;
		} finally {
			@ldap_unbind($connect);
		}
	}

	public function loginOut() {

	    //结束用户状态 - 坐席退出则应该，结束状态日志记录
        $seat_id = session('uinfo.seat_id');
        $secure = false;
		$masterName = session('uinfo.mastername');
        if (UserRepository::isSeat()) {
			$exeResult = (new SeatManage())->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_OFFLINE, ['id'=>$seat_id]);
        }
		session()->flush();
		UserRepository::deleteLoginSessionOnRedis();
        if(config('common.is_use_new_order_apply')){
            $redis_obj = new RedisCommon();
            $redis_obj->zRem(config("common.auto_apply_seat_key"),$seat_id);
        }
		//sso登出日志
		CommonApi::ssoLoginLog(CommonApi::SSO_LOG_LOGOUT, $masterName);
        if(Helper::isProduction()){
            $secure = true;
        }
		return redirect('/login', 302, [], $secure);
	}
}

?>