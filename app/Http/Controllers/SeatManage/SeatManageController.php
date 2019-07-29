<?php
namespace App\Http\Controllers\SeatManage;


use App\Http\Controllers\BaseController;
use App\Library\Helper;
use App\Library\RedisCommon;
use App\Models\VideoVisa\Admin;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\SeatManage;
use App\XinApi\ErpApi;
use Illuminate\Http\Request;
use App\Repositories\SeatManageRepository;
use App\Repositories\UserRepository;
use App\Repositories\CityRepository;
use App\Repositories\ImRepository;
use Illuminate\Support\Facades\DB;


/**
 * 坐席管理控制器
 */
class SeatManageController extends BaseController
{

    private $env = '';
    private $seat_key = '';

    public function __construct()
    {
        $this->env = Helper::isProduction() ? 'production' : '';;
        $this->seat_key = config('common.auto_apply_seat_key');
    }

    public function index(Request $request){

        $request = $request->all();

        $condition = [];
        if (isset($request['mastername']) && !empty($request['mastername'])) {
            $condition['mastername'] = trim(strip_tags($request['mastername']));
        }
        if (isset($request['fullname']) && !empty($request['fullname'])) {
            $condition['fullname'] = trim(strip_tags($request['fullname']));
        }
        if (isset($request['deptname']) && !empty($request['deptname'])) {
            $condition['deptname'] = trim(strip_tags($request['deptname']));
        }
        if (isset($request['mobile']) && !empty($request['mobile'])) {
            $condition['mobile'] = trim(strip_tags($request['mobile']));
        }
        if (isset($request['email']) && !empty($request['email'])) {
            $condition['email'] = trim(strip_tags($request['email']));
        }

        $request['pagesize'] = !empty($request['pagesize']) ? $request['pagesize'] : 5;

        //获取列表
        $seat_repos = new SeatManageRepository();
        $fields = ['id', 'mastername', 'fullname', 'deptname',
            'mobile', 'email', 'create_time', 'status'];
        $list = $seat_repos->getListByCondition($fields, $condition, $request['pagesize']);


        //获取城市列表
        $city_pos = new CityRepository();
        $city_list = $city_pos->getAllCity(['cityid','cityname']);


        return view('seat_manage.index', [
            'list'=>$list,
            'request'=>$request,
            'city_list' => $city_list
        ]);
    }

    //添加坐席
    public function add(Request $request){
        $request = $request->all();
//        $source = isset($request['source'])?$request['source']:'erp';
        $erp_master_id = isset($request['masterid'])?$request['masterid']:0;
        $erp_master_params = [];
        $erp_master_params['mastername'] = isset($request['mastername'])?$request['mastername']:'';
        $erp_master_params['fullname'] = isset($request['fullname'])?$request['fullname']:'';
        $erp_master_params['email'] = isset($request['email'])?$request['email']:'';
        $erp_master_params['mobile'] = isset($request['mobile'])?$request['mobile']:'';
        $erp_master_params['deptname'] = isset($request['deptname'])?$request['deptname']:'';
        $city = isset($request['city'])?$request['city']:'';

        $nickname = isset($request['nickname'])?$request['nickname']:'';
        $gender = isset($request['gender'])?$request['gender']:'';

        if(!$erp_master_params['email']){
            return $this->showMsg(self::CODE_FAIL,'缺少参数',[]);
        }

//        if(!$erp_master_id && $source == 'erp'){
//            return $this->showMsg(self::CODE_FAIL,'请选择员工',[]);
//        }

        if(!$nickname){
            return $this->showMsg(self::CODE_FAIL,'请填写昵称',[]);
        }

        //检测坐席
        $seat_pos = new SeatManageRepository();
        $check_seat = $seat_pos->checkSeat(['email'=>$erp_master_params['email']]);
        if($check_seat == true){
            return $this->showMsg(self::CODE_FAIL,'坐席已经存在!',[]);
        }

        //是否是管理员
        $isAdmin = (new Admin())->getOne(['id'], ['email'=>$erp_master_params['email']]);
        if ($isAdmin) {
            return $this->showMsg(self::CODE_FAIL, '已是管理员，不能添加为坐席');
        }

        //是否是超级管理员
        if (in_array($erp_master_params['mastername'], UserRepository::$root)) {
            return $this->showMsg(self::CODE_FAIL, '已是超级管理员，不能添加为坐席');
        }

        //如果是LDAP，则添加到erp_master
        /*if($source == 'ldap' && !$erp_master_id && !empty($city)){
            $erp_master_params['cityid'] = $city;
            $erp_master_params['isdealer'] = 0;
            $erp_master_params['remark'] = 'fast.youxinjinrong.com';

            if($erp_master_params['email']){
                $user_model = new UserRepository();
                $check_user = $user_model->checkUser(['email'=>$erp_master_params['email']]);
                if(!empty($check_user)) {
                    $erp_master_id = $check_user['masterid'];
                } else {
                    $erp_master_id = $user_model->addUsers($erp_master_params);
                    if(!$erp_master_id) return $this->showMsg(self::CODE_FAIL,'新增用户失败',[]);
                }
            }
        }*/

        //请求网易IM，注册新用户
        $im_params = [
            'accid' => $erp_master_params['mastername'] . '_' . $this->env . '_seat',
            'name' => $nickname,
            'gender' => $gender,
        ];
        $im_repos = new ImRepository();
        $credit_user_im = $im_repos->createUser($im_params);
        /*$credit_user_im = [
            'code' => 1,
            'msg' => '成功',
            'data' => [
                'code' => 200,
                'info' => [
                    'token' => 'ab0ef3eaa975f5c526903b971d0384dc',
                    'accid' => 'wangqing1',
                    'name' => 'wangqing11111',
                ],
            ],
        ];*/
        if($credit_user_im['code'] != self::CODE_SUCCESS){
            return $this->showMsg(self::CODE_FAIL, $credit_user_im['msg'], []);
        }
        $im_data = $credit_user_im['data'];


        //开启事务
        DB::connection('mysql.video_visa')->beginTransaction();

        $time = time();
        //添加网易账号新
        $account_params = [
            'accid' => isset($im_data['info']['accid'])?$im_data['info']['accid']:$im_params['accid'],
            'token' => isset($im_data['info']['token'])?$im_data['info']['token']:'',
            'gender' => $gender,
            'nickname' => $nickname,
            'create_time' => $time,
            'update_time' => $time,
        ];
        $add_im = $seat_pos->addImAccount($account_params);
        if(!$add_im){
            DB::connection('mysql.video_visa')->rollback();
            return $this->showMsg(self::CODE_FAIL, '添加IM账号信息失败', []);
        }

        //添加坐席管理表
        $params = [
            'master_id' => $erp_master_id,
            'mastername' => $erp_master_params['mastername'],
            'fullname' => $erp_master_params['fullname'],
            'deptname' => $erp_master_params['deptname'],
            'mobile' => $erp_master_params['mobile'],
            'email' => $erp_master_params['email'],
            'im_account_id' => $add_im,
            'work_status' => SeatManage::SEAT_WORK_STATUS_OFFLINE,
            'create_time' => $time,
            'update_time' => $time,
        ];
        $add_seat = $seat_pos->addData($params);
        if(!$add_seat){
            DB::connection('mysql.video_visa')->rollback();
            return $this->showMsg(self::CODE_FAIL, '添加坐席失败', []);
        }

        //事务结束
        DB::connection('mysql.video_visa')->commit();
        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
    }

    //坐席添加页面
    public function addPage(Request $request){
        //获取城市列表
        $city_pos = new CityRepository();
        $city_list = $city_pos->getAllCity(['cityid','cityname']);
        return view('seat_manage.add', [
            'city_list' => $city_list
            ]);
    }

    //修改坐席页面
    public function editPage(Request $request){
        $id = $request->input('id', 0);
        if(!$id){
            return $this->showMsg(self::CODE_FAIL, '缺少参数', []);
        }

        //获取单条坐席记录
        $seat_pos = new SeatManageRepository();
        $info = $seat_pos->getOneInfoById($id, ['im_account_id', 'id', 'mastername', 'fullname',
            'email', 'mobile', 'deptname']);


        //获取网易账号信息
        if($info['im_account_id']) {
            $im_info = $seat_pos->getImAccountInfo($info['im_account_id'], ['gender', 'nickname']);
            $info['gender'] = $im_info['gender'];
            $info['nickname'] = $im_info['nickname'];
        } else {
            $info['gender'] = 0;
            $info['nickname'] = '';
        }


        return view('seat_manage.edit', [
            'info' => $info
        ]);
    }

    //更新坐席信息
    public function edit(Request $request){
        $id = $request->input('id', 0);
        $gender = $request->input('gender', 1);
        $nickname = $request->input('nickname', '');

        if(!$id){
            return $this->showMsg(self::CODE_FAIL, '缺少参数', []);
        }
        if(!$nickname){
            return $this->showMsg(self::CODE_FAIL, '请填写昵称', []);
        }

        //获取一条数据
        $seat_pos = new SeatManageRepository();
        $info = $seat_pos->getOneInfoById($id, ['im_account_id']);

        //网易账号
        $im_info = [];
        if($info['im_account_id']){
            $im_info = $seat_pos->getImAccountInfo($info['im_account_id'], ['nickname', 'accid', 'gender']);
        }
        $old_nick = isset($im_info['nickname']) ? $im_info['nickname'] : '';

        if(($old_nick && $old_nick != $nickname) || $im_info['gender'] != $gender){

            //更新网易账号
            $im_params = [
                'accid' => $im_info['accid'],
                'gender' => $gender,
                'name' => $nickname,
            ];
            $im_repos = new ImRepository();
            $up_im = $im_repos->updateUser($im_params);
            if($up_im['code'] != self::CODE_SUCCESS){
                return $this->showMsg(self::CODE_FAIL, $up_im['msg'], []);
            }

            $save_params = [
                'gender' => $gender,
                'nickname' => $nickname,
                'update_time' => time(),
            ];
            $save_data = $seat_pos->saveImData($save_params, $info['im_account_id']);
            if(!$save_data){
                return $this->showMsg(self::CODE_FAIL, '坐席更新失败', []);
            }
        }
        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
    }


    //获取master
    public function getErpMater(Request $request){
        $search_text = $request->input('search_text', '');
        if(!$search_text){
            return $this->showMsg(self::CODE_FAIL, '缺少参数', []);
        }
        $master_info = ErpApi::getMasterInfo(['master_names'=>[$search_text]]);
        if ($master_info['code'] == 1) {
            if (isset($master_info['data'][0])) {
                $master_info = $master_info['data'][0];
                //查询城市
                $city_pos = new CityRepository();
                $cityid = $master_info['cityid'];
                $master_info['cityname'] = $city_pos->getCityInfoByid($cityid);
                return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $master_info);
        }
        //不存在则从LDAP中查询
        $use_pos = new UserRepository();
        $user_info = $use_pos->getLdapInfo($search_text);
        if(empty($user_info)){
            return $this->showMsg(self::CODE_FAIL, '非公司员工！');
        }
        $user_info['source'] = 'ldap';
        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $user_info);
        }
    }


    //获取坐席信息
    public function getSeatInfo(){
        $seat_id = session('uinfo.seat_id');
        $seatInfo = (new SeatManageRepository())->getOneInfoById($seat_id, ['work_status']);
        $seatInfo['seat_id'] = $seat_id;

        return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS, $seatInfo);
    }

    //修改坐席状态
    public function editStatus(Request $request){
        $id = $request->input('id', 0);
        $status = $request->input('status', 0);
        if(!$id || !$status){
            return $this->showMsg(self::CODE_FAIL, '缺少参数', []);
        }

        //获取一条坐席数据
        $seat_repos = new SeatManageRepository();
        $seat_info = $seat_repos->getOneInfoById($id, ['im_account_id', 'status']);
        if(!$seat_info){
            return $this->showMsg(self::CODE_FAIL, '未找到坐席数据', []);
        }

        //获取网易账号信息
        $im_info = $seat_repos->getImAccountInfo($seat_info['im_account_id'], ['accid']);

        $seat_status = $seat_info['status'];
        //更新网易IM用户状态
        if($status == 1 && $seat_status != $status && $im_info){
            //解除封禁
            $im_repos = new ImRepository();
            $block_im = $im_repos->unblockUser($im_info['accid']);
            if($block_im['code'] != self::CODE_SUCCESS){
                return $this->showMsg(self::CODE_FAIL, $block_im['msg'], []);
            }
        } elseif($status == 2 && $seat_status != $status && $im_info){
            //封禁
            $im_repos = new ImRepository();
            $block_im = $im_repos->blockUser($im_info['accid']);
            if($block_im['code'] != self::CODE_SUCCESS){
                return $this->showMsg(self::CODE_FAIL, $block_im['msg'], []);
            }
        }

        $save = $seat_repos->editStatus($id, $status);
        if($save){
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, []);
        }
        return $this->showMsg(self::CODE_FAIL, self::MSG_FAIL, []);
    }

    //修改坐席状态 切换状态
    public function setSeatStatus(Request $request)
    {
        $work_status = $request->input('work_status', 0);
        if(!$work_status){
            return $this->showMsg(self::CODE_FAIL, self::MSG_PARAMS);
        }
        $seat_id = session('uinfo.seat_id');
        $visaObj = new FastVisa();
        $hasVisaInfo = $visaObj->where(['seat_id'=>$seat_id])->whereIn('status',[3,4])->first();
        if (!empty($hasVisaInfo)) {
            return $this->showMsg(self::CODE_FAIL, '有处理中的单子，不允许改变状态');
        }
        //繁忙中不可更改状态
        $seat_pos = new SeatManageRepository();
        $seat_info = $seat_pos->getSeatInfoByMasterId(['id','work_status'], $seat_id);

        if(!$seat_info){
            return $this->showMsg(self::CODE_FAIL, '未找到坐席信息');
        }

        if($seat_info['work_status'] == $work_status){
            $msg = '您当前就处于' . SeatManage::$statusNameList[$work_status] . '状态';
            return $this->showMsg(self::CODE_FAIL, $msg);
        }

        if($seat_info['work_status'] == SeatManage::SEAT_WORK_STATUS_BUSY){
            return $this->showMsg(self::CODE_FAIL, '坐席处于繁忙状态，禁止更改状态');
        }

        //更新状态
        $update = (new SeatManage())->updateWorkSeatStatus($work_status, ['id'=>$seat_id]);

        if(!$update['code'] != 1){
            return $this->showMsg(self::CODE_FAIL, $update['msg']);
        }
        //成功分配订单后就不弹窗显示切换在线状态成功了，影响分配订单提示
        $hasVisaInfo = $visaObj->where(['seat_id'=>$seat_id])->whereIn('status',[3,4])->first();
        if(config('common.is_use_new_order_apply')){
            $redis_obj = new RedisCommon();
            //切换为空闲，加入空闲队列
            if($work_status == SeatManage::SEAT_WORK_STATUS_FREE && !empty($seat_id)) {
                $order_arr = $redis_obj->zRangeByScore(config('common.auto_apply_order_key'), $seat_id, $seat_id, array('limit' => array(0, 1)));
                if(empty($order_arr)){
                    $get_res = $visaObj->getOne(
                        ['id','line_up_time'],
                        ['status'=>FastVisa::VISA_STATUS_HANG_QUEUEING,'seat_id' => $seat_id],['line_up_time' => 'asc']);
                    if($get_res){
                        $redis_obj->zadd(config('common.auto_apply_order_key'),$seat_id,$get_res['id']);
                    }
                }
                $redis_obj->zadd($this->seat_key,time(),$seat_id);
            }else{
                $redis_obj->zRem($this->seat_key,$seat_id);
            }
        }
        if (!empty($hasVisaInfo)) {
            return $this->showMsg(self::CODE_NO_ALERT, '成功分配订单');
        }
        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
    }

    /**
     * 坐席重置列表
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function applyResetList(Request $request){
        //获取所有的坐席
        $seat_repos = new SeatManageRepository();
        $seatList = $seat_repos->getAllByCondition(['id','fullname']);

        //获取所有挂起面签并按照坐席id分组
        $visaHangListRaw = (new FastVisa())->getAll(['id', 'full_name', 'seat_id'], ['status'=>FastVisa::VISA_STATUS_HANG]);
        $visaHangList = [];
        if ($visaHangListRaw) {
            foreach($visaHangListRaw as $visa) {
                $visaHangList[$visa['seat_id']][] = $visa;
            }
        }

        $list = [];
        foreach($seatList as $seat) {
            $list[] = [
                'seat_id' => $seat['id'],
                'seat_name' => $seat['fullname'],
                'seat_count' => isset($visaHangList[$seat['id']]) ? count($visaHangList[$seat['id']]) : 0,
                'user_list' => isset($visaHangList[$seat['id']]) ? $visaHangList[$seat['id']] : [],
            ];
        }

        return view('seat_manage.reset_list', [
            'seat_list' => $seatList,
            'list' => $list,
        ]);
    }

    /**
     * 坐席重置
     * @param Request $request
     * @return mixed
     */
    public function applyReset(Request $request)
    {
        $visaIds = $request->input('visa_ids', 0);
        $visaIds = explode(',', $visaIds);

        $visaModel = new FastVisa();
        $visaLogModel = new FastVisaLog();
        $visas = $visaModel->getAll(['id', 'seat_id', 'seat_name', 'master_id'], ['in'=>['id'=>$visaIds]]);

        DB::connection('mysql.video_visa')->beginTransaction();
        foreach ($visas as $visa) {
            //除状态外其他的更新字段
            $extraUpdateData = [
                'seat_id'=>0,
                'seat_name'=>'',
                'prev_seat_id'=>$visa['seat_id'],
                'prev_seat_name'=>$visa['seat_name'],
            ];

            //执行更新
            $where = ['id'=>$visa['id'], 'status'=>FastVisa::VISA_STATUS_HANG];
            $exeResult = $visaModel->updateVisaStatus(FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT, $where, $extraUpdateData);
            if (!$exeResult) {
                DB::connection('mysql.video_visa')->rollback();
                $this->showMsg(self::CODE_FAIL, '更新数据失败');
            }

            //插入新log
            $newVisaLogData = [
                'visa_id' => $visa['id'],
                'master_id' => $visa['master_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'queuing_time' => time(),
            ];
            $newLogId = $visaLogModel->insertVisaLog($newVisaLogData);
            if (!$newLogId) {
                DB::connection('mysql.video_visa')->rollback();
                $this->showMsg(self::CODE_FAIL, '插入log失败，visa_id:' . $visa['id']);
            }
        }

        DB::connection('mysql.video_visa')->commit();
        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
    }
}