<?php
namespace App\Http\Controllers\Repair;


use App\Http\Controllers\BaseController;
use App\Library\Helper;
use App\Repositories\ImRepository;
use Illuminate\Http\Request;
use App\Models\Xin\RbacMaster as xin_rbac;
use App\Models\NewCar\RbacMaster as newcar_rbac;
use App\Models\VideoVisa\ImAccount;
use App\Models\VideoVisa\ImRbacMaster;


/**
 * 修补账号数据
 */
class ImAccountController extends BaseController {

    private $env = '';

    public function __construct()
    {
        $this->env = Helper::isProduction() ? 'production' : '';
    }

	//注册网易账号
    public function registImAccount(Request $request){
        $masterid = $request->input('masterid', '');
        $channel_type = $request->input('channel_type', 1);

        if(!$masterid || !in_array($channel_type, [1,2])){
            return $this->showMsg(self::CODE_FAIL, self::MSG_PARAMS);
        }

        list($accid, $master_info) = $this->getMaster($masterid, $channel_type);
        if(!$accid || !$master_info){
            return $this->showMsg(self::CODE_FAIL, '未找到销售人员信息');
        }

        //注册网易账号
        $im_repos = new ImRepository();
        $credit_user_im = $im_repos->createUser(['accid'=>$accid,'name'=>$master_info['fullname']]);
        var_export($credit_user_im);exit;
    }

    //更新 im_rbac_master
    public function updateImMaster(Request $request){
        $masterid = $request->input('masterid', '');
        $channel_type = $request->input('channel_type', 1);

        if(!$masterid || !in_array($channel_type, [1,2])){
            return $this->showMsg(self::CODE_FAIL, self::MSG_PARAMS);
        }

        list($accid, $master_info) = $this->getMaster($masterid, $channel_type);
        if(!$accid || !$master_info){
            return $this->showMsg(self::CODE_FAIL, '未找到销售人员信息');
        }

        $im_repos = new ImRepository();
        $im_info = $im_repos->getUinfos([$accid]);
        if($im_info['code'] != self::CODE_SUCCESS){
            return $this->showMsg(self::CODE_FAIL, '未注册网易账号');
        }

        $im_data = $im_info['data']['uinfos'][0];
        //查看 im_account是否存在
        $times = time();
        $im_account = new ImAccount();
        $im_rbac_master = new ImRbacMaster();
        $im_account_info = $im_account->getOne(['id'],['accid' => $accid]);
        $im_account_id = isset($im_account_info['id'])?$im_account_info['id']:'';
        if(!$im_account_id){
            //更新token
            $token_data = $im_repos->refreshToken($accid);
            if($token_data['code'] != self::CODE_SUCCESS){
                return $this->showMsg(self::CODE_FAIL, '更新token失败');
            }
            $token = $token_data['data']['info']['token'];
            //新增
            $im_params = [
                'accid' => $accid,
                'nickname' => $im_data['name'],
                'token' => $token,
                'update_time' => $times,
                'create_time' => $times,
            ];

            $im_account_id = $im_account->insertGetId($im_params);
        }

        //新增im_rbac_master
        $im_account_info = $im_rbac_master->getOne(['id','im_account_id'],['masterid' => $masterid,'channel_type'=>$channel_type]);
        $old_im_id = isset($im_account_info['im_account_id'])?$im_account_info['im_account_id']:'';
        if($im_account_info && $old_im_id != $im_account_id){
            $im_rbac_master->updateBy(['im_account_id'=>$im_account_id],['id'=>$im_account_info['id']]);
            echo "操作完成";exit;
        }

        if(!$im_account_info){
            $im_master_params = [
                'masterid' => $masterid,
                'mastername' => $master_info['mastername'],
                'channel_type' => $channel_type,
                'email' => $master_info['email'],
                'mobile' => $master_info['mobile'],
                'fullname' => $master_info['fullname'],
                'im_account_id' => $im_account_id,
                'create_time' => $times,
            ];
            $im_rbac_master->insert($im_master_params);
        }
        echo "操作完成";exit;
    }


    //获取rbac_mastre
    private function getMaster($masterid, $channel_type){
        $obj = null;
        $accid_pre = '';
        if($channel_type == 1){
            $obj = new xin_rbac();
            $accid_pre = 'cjb';
        } else {
            $obj = new newcar_rbac();
            $accid_pre = 'xcb';
        }

        //获取销售信息
        $master_fields = ['masterid','mastername','email','mobile','fullname'];
        $master_info = $obj->getOne($master_fields,['masterid'=>$masterid]);
        if(!$master_info){
            return ['',''];
        }
        $accid = $master_info['mastername'] . '_' . $this->env . '_' . $accid_pre;
        return [$accid, $master_info];
    }






}