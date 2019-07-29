<?php
namespace App\Http\Controllers\QrCode;

use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Library\RedisCommon;
use App\Library\RedisObj;
use App\Library\QRcode;
use App\Models\VideoVisa\Role;
use App\Repositories\Visa\ActionRepository;
use App\XinApi\CommonApi;
use App\Models\VideoVisa\ErrorCodeLog;
use App\Repositories\UserRepository;
use App\Models\VideoVisa\SeatManage;
use Illuminate\Http\Request;
use App\Models\VideoVisa\Admin;

class QrCodeController extends BaseController {

    public $seatModel = null;
    public $roleModel = null;
    public function __construct() {
        $this->seatModel = new SeatManage();
        $this->roleModel = new Role();
    }

    public function qrcodeCallback(Request $request) {

        $params = $request->all();
        $data = !empty($params['data']) ? $params['data'] : "";
        if(!$data){
            return response()->json(['status' => -1, 'msg' => '操作失败', 'data' => []]);
        }

        $data = json_decode($data, true);
        //校验sn的值是否正确
        $snFrom = $data['sn'];
        $name = $data['name'];
        $token = $data['token'];
        $str_key = "name=".$name."&token=".$token;
        $qrcode_key = config('common.qrcode_key');
        $time_key = date('Y-m-d');
        $sn = strtolower(md5($str_key.$qrcode_key.$time_key));
        if(strcmp($sn, $snFrom) != 0) {
            return response()->json(['status' => -1, 'msg' => '操作失败', 'data' => []]);
        }

        //校验成功，向redis添加信息
        UserRepository::setQrcodeUserNameOnRedis($token, $name);

        return response()->json(['status' => 0, 'msg' => '操作成功', 'data' => []]);
    }

    public function CheckQrcodeLogin() {

        $userName = UserRepository::getQrcodeUserNameOnRedis();
        if(empty($userName)) {
            return response()->json(['code' => -1, 'msg' => '未登录']);
        }
        UserRepository::deleteQrcodeUserNameOnRedis();
        ///
        $uinfo['mastername'] = $userName;
        $seatInfo = $this->seatModel->select('id','work_status','status','master_id','roleid','fullname','deptname')->where(['mastername' => $uinfo['mastername'], 'status'=>1])->first();
        if (!empty($seatInfo)) {
            $seatInfo = $seatInfo->toArray();
            if($seatInfo['status'] == 2) {
                return json_encode(['code' => -1, 'msg' => '用户已被禁用!']);
            }
            $role_flag = $this->roleModel->getOne(['roleid','flag','role_data'],['roleid'=>$seatInfo['roleid']]);
            $uinfo['role'] = $seatInfo['roleid'];
            $uinfo['fullname'] = $seatInfo['fullname'];
            $uinfo['deptname'] = $seatInfo['deptname'];
            $uinfo['flag'] = $role_flag['flag'];
            $uinfo['seat_id'] = $seatInfo['id'];
            $uinfo['master_id'] = $seatInfo['master_id'];
//            $uinfo['action'] = json_decode($role_flag['role_data'],true);
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
        UserRepository::setLoginSessionOnRedisNoStart();
        CommonApi::ssoLoginLog(CommonApi::SSO_LOG_LOGIN, $uinfo['mastername']);
        ///
        return response()->json(['code' => 1, 'msg' => '登录成功']);
    }
}