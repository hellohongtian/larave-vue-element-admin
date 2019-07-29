<?php

namespace App\Http\Controllers\System;
use App\Http\Controllers\BaseController;
use App\Library\Helper;
use App\Library\RedisCommon;
use App\Models\VideoVisa\Admin;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\SeatManage;
use App\Models\XinFinance\CarHalfService;
use App\Repositories\Visa\ActionRepository;
use App\Repositories\Visa\RoleRepository;
use App\XinApi\ErpApi;
use Illuminate\Http\Request;
use App\Repositories\SeatManageRepository;
use App\Repositories\UserRepository;
use App\Repositories\CityRepository;
use App\Repositories\ImRepository;
use Illuminate\Support\Facades\DB;
use App\Models\VideoVisa\ImAccount;
/**
 * 用户管理控制器
 */
class UserController extends BaseController
{

    private $env = '';
    private $seat_key = '';

    public function __construct()
    {
        $this->env = Helper::isProduction() ? 'production' : '';;
        $this->seat_key = config('common.auto_apply_seat_key');
    }
    /**
     * 用户首页
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed]
     */
    public function index(Request $request){
        if($request->isMethod('post')){
            $request = $request->all();
            $condition = [];
            if (isset($request['mastername']) && !empty($request['mastername'])) {
                $condition['mastername'] = trim(strip_tags($request['mastername']));
            }
            if (isset($request['fullname']) && !empty($request['fullname'])) {
                $condition['fullname'] = trim(strip_tags($request['fullname']));
            }
            if (isset($request['mobile']) && !empty($request['mobile'])) {
                $condition['mobile'] = trim(strip_tags($request['mobile']));
            }
            if (isset($request['email']) && !empty($request['email'])) {
                $condition['email'] = trim(strip_tags($request['email']));
            }
            if (isset($request['roleid']) && !empty($request['roleid'])) {
                $condition['roleid'] = trim(strip_tags($request['roleid']));
            }
            $request['limit'] =  !empty($request['limit'])? $request['limit']:5;
            //获取列表
            $seat_manage = new SeatManage();
            $count = $seat_manage->countBy($condition);
            $fields = ['id', 'mastername', 'fullname', 'deptname','mobile', 'email', 'create_time', 'status','roleid'];
            $seat_list = $seat_manage->getList($fields, $condition,[],[],$request['limit']);
            $data = $seat_list->toArray()['data'];
            $relo_rep = new RoleRepository();
            if(!empty($data)){
                $data = array_map(function($v) use($relo_rep){
                    $v['create_time'] = date("Y-m-d",$v['create_time']);
                    $v['role_name'] = $relo_rep->get_role_list()[$v['roleid']];
                    return $v;
                },$data);
            }
            return $this->_page_format($data,$count);
        }else{
            //获取城市列表
            $city_pos = new CityRepository();
            $city_list = $city_pos->getAllCity(['cityid','cityname']);
            return view('user.index1', [
                'city_list' => $city_list,
                'role_arr' => (new RoleRepository())->get_role_list()
            ]);
        }

    }

    /**
     * 添加用户
     * @param Request $request
     * @return mixed
     */
    public function add(Request $request){
        $request = $request->all();
        if(!UserRepository::isAdmin() && !UserRepository::isRoot() && !UserRepository::isDeveloper()){
            return $this->showMsg(self::CODE_FAIL,'权限不足!',[]);
        }
        $erp_master_id = isset($request['masterid'])?$request['masterid']:0;
        $erp_master_params = [];
        $erp_master_params['mastername'] = isset($request['mastername'])?$request['mastername']:'';
        $erp_master_params['fullname'] = isset($request['fullname'])?$request['fullname']:'';
        $erp_master_params['email'] = isset($request['email'])?$request['email']:'';
        $erp_master_params['mobile'] = isset($request['mobile'])?$request['mobile']:'';
        $erp_master_params['deptname'] = isset($request['deptname'])?$request['deptname']:'';
        $erp_master_params['roleid'] = isset($request['role'])?$request['role']:'';
        $erp_master_params['gender'] = isset($request['gender'])?$request['gender']:'';
        $apply_group = intval(($erp_master_params['roleid'] == 2)? isset($request['seat_group'])?$request['seat_group']:0:0);
        $nickname = isset($request['nickname'])?$request['nickname']:'';
        $gender = isset($request['gender'])?$request['gender']:'';
        foreach ($erp_master_params as $item) {
            if(empty($item)){
                return $this->showMsg(self::CODE_FAIL,'参数错误!',[]);
            }
        }
        if($erp_master_params['roleid'] == 2 && empty($apply_group)){
            return $this->showMsg(self::CODE_FAIL,'参数错误!',[]);
        }
        $seat_model = new SeatManage();
        $check_seat = $seat_model->getOne(['id','status'], ['email' => $erp_master_params['email']]);
        if ($check_seat) {
            return $this->showMsg(self::CODE_FAIL, '用户已经存在!', []);
        }
        //开启事务
        DB::connection('mysql.video_visa')->beginTransaction();
        //请求网易IM，注册新用户
        $accid = $erp_master_params['mastername'] . '_' . $this->env . '_seat';
        $time = time();
        try{
            //添加im账号
            $account_params = [
                'accid' => $accid,
                'token' => '',
                'gender' => $gender,
                'nickname' => $nickname,
                'create_time' => $time,
                'update_time' => $time,
            ];
            $im_account = new ImAccount();
            $add_im = $im_account->insertGetId($account_params);
            if(!$add_im){
                throw new \Exception('添加IM账号信息失败');
            }
            if($erp_master_params['roleid'] == 1){
                $erp_master_params['flag'] = 2;
            }elseif($erp_master_params['roleid'] == 2){
                $erp_master_params['flag'] = 3;
            }
            //添加坐席管理表
            $params = [
                'roleid' => $erp_master_params['roleid'],
                'flag' => $erp_master_params['flag'],
                'master_id' => $erp_master_id,
                'mastername' => $erp_master_params['mastername'],
                'fullname' => $erp_master_params['fullname'],
                'gender' => $erp_master_params['gender'],
                'deptname' => $erp_master_params['deptname'],
                'mobile' => $erp_master_params['mobile'],
                'email' => $erp_master_params['email'],
                'im_account_id' => $add_im,
                'work_status' => SeatManage::SEAT_WORK_STATUS_OFFLINE,
                'apply_group' => $apply_group,
                'create_time' => $time,
                'update_time' => $time,
            ];
            $add_seat = $seat_model->insertGetId($params);
            if(!$add_seat){
                throw new \Exception('添加用户失败');
            }
            //事务结束
            DB::connection('mysql.video_visa')->commit();
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
        }catch (\Exception $e){
            DB::connection('mysql.video_visa')->rollback();
            return $this->showMsg(self::CODE_FAIL,$e->getMessage(),[]);
        }
    }

    /**
     * 编辑用户
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed|string
     */
    public function edit(Request $request)
    {
        $seat_model = new SeatManage();
        $id = (integer)$request->input('id',0);
        if(empty($id)){
            return '出现未知错误!';
        }
        $role_arr = (new RoleRepository())->get_role_list();
        if ($request->isMethod('POST')) {
            // 校验
            $this->validate($request, [
                'roleid' => 'required',
                'is_exec' => 'required',
            ],[
                'required' => ':attribute 为必填项',
            ],[
                'roleid' => '角色',
                'is_exec' => '是否启用',
            ]);
            $params['roleid'] = (string)$request->input('roleid',1);
            $params['status'] = (integer)$request->input('is_exec',1);
            try{
                if($params['roleid'] == 1){//管理员
                    $params['flag'] = 2;
                }elseif($params['roleid'] == 2){//初审坐席
                    $params['flag'] = 3;
                    $apply_group = (integer)$request->input('seat_group',0);
                    if (!in_array($apply_group,array_keys(CarHalfService::group_map()))) {
                        return $this->showMsg(self::CODE_FAIL,'操作失败!');
                    }
                    $params['apply_group'] = $apply_group;
                }
                $update_res = $seat_model->where(['id'=> $id])->update($params);
                if($update_res){
                    RedisCommon::init()->delete(ActionRepository::FAST_USE_MENU_LIST_KEY.$id);
                }
            }catch (\Exception $e){
                $err_msg = $e->getMessage();
                return $this->showMsg(self::CODE_FAIL,$err_msg);
            }
            return $this->showMsg(self::CODE_SUCCESS, '操作成功!');
        }else{
            $this->validate($request, [
                'id' => 'required',
            ],[
                'required' => ':attribute 为必填项',
            ],[
                'id' => 'id',
            ]);
            $id = (int)$request->input('id');
            if(!empty($id)){
                $edit_data =  $seat_model->getOne(['*'],  ['id' => $id]);
                return view('user.edit',array_merge($edit_data,['id' => $id,'role_arr' => $role_arr]));
            }
        }
    }
}