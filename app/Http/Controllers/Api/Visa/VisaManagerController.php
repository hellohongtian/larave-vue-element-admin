<?php
namespace App\Http\Controllers\Api\Visa;
/**
 * 面签接口
 * Date: 2018/1/3
 */


use App\Fast\FastException;
use App\Fast\FastGlobal;
use App\Http\Controllers\Api\BaseApiController;
use App\Library\Helper;
use App\Models\VideoVisa\FastTransferVisa;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaExtend;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\VisaRemark;
use App\Repositories\Api\InitialFaceRepository;
use App\Repositories\Async\AsyncInsertRepository;
use Illuminate\Http\Request;
use App\Models\VideoVisa\VisaPool;
use Illuminate\Support\Facades\DB;
use App\Models\VideoVisa\ImAccount;
use App\Models\VideoVisa\ImRbacMaster;
use App\Repositories\ImRepository;
use App\Models\NewCar\RbacMaster as newcar_rbac;
use App\Models\Xin\RbacMaster as xin_rbac;
use App\Library\Common;
use App\Library\RedisCommon;
use App\Models\VideoVisa\VisaRemarkAttach;
use App\Models\Xin\CarHalfApply;
use App\Repositories\ApplyRepository;
use App\Repositories\WebRTCSigApi;
use App\Models\VideoVisa\SeatManage;
use App\Repositories\FastVisaRepository;

class VisaManagerController extends BaseApiController
{
    const ERROR_CODE = -1;  //已知错误，超级宝展示我们返回的错误信息
    const EXCEPTION_CODE = -2;  //未知错误，超级宝统一显示操作失败

    //待处理type值
    const POLL_TYPE_V = 0;

    //已完成type值
    const REMARK_TYPE_V = 1;

    //待处理包含的状态 1未排队 2待处理(已排队未领取) 3处理中(已领取未视频) 4视频中 8重新排队
    protected $type_poll_status = [1,2,3,4,8];

    //已完成包含的状态 5审核通过 6审核拒绝 7跳过面签
    protected $type_remark_status = [5,6,7];

    //异常数据邮件
    protected $api_error_email;

    protected $visa_pool;
    protected $visa_remark;
    protected $redis;

    private $env = '';
    private $order_key = '';

    public function __construct()
    {
        $this->api_error_email = config('mail.developer');
        $this->visa_pool = new VisaPool();
        $this->visa_remark = new VisaRemark();
        $this->redis = new RedisCommon();
        $this->env = Helper::isProduction() ? 'production' : '';
        $this->order_key = config('common.auto_apply_order_key');

    }

    public function index(Request $request){

    }

    //处理列表
    public function getList(Request $request){
        $uri = $request->capture()->getPathInfo();
        $masterId = $request->input('masterid', '');
        try {
            $result = $this->doGetList($request);
            $resData = [
                'code' => self::CODE_SUCCESS,
                'message' => self::MSG_SUCCESS,
                'data' => $result
            ];
//            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$resData);
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $result);
        }catch (\Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            $result = ['code' => $code, 'message' => $message];
            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$result);
            if ($code == self::ERROR_CODE)
                return $this->showMsg($code, $message, []);
            else
                return $this->showMsg(self::EXCEPTION_CODE, $message, []);
        }
    }

    private function doGetList($request){
        FastGlobal::$retLog = 4;
        $masterId = $request->input('masterid', '');
        $channel_type = $request->input('channel_type', 1);
        $type = $request->input('type', 0);
        $page_size = $request->input('page_size', 15);
        $channel_type = empty($channel_type) ? 1 :  $channel_type;
        if(!$masterId || !in_array($channel_type, [1,2])){
            FastException::throwException(self::MSG_PARAMS, self::ERROR_CODE);
        }

        if(!in_array($type, [0,1])){
            FastException::throwException(self::MSG_PARAMS, self::ERROR_CODE);
        }

        $visaObj = new FastVisa();
        if($type == self::POLL_TYPE_V){
            $status = [1,2,3,4,8,9,10,11];
        } else {
            $status = [5,6,7];
        }
        $fileds = ['id','status','apply_id','user_id','full_name','mobile','car_id','inputted_id','car_name','reconsideration_status'];

        $where = [
            'master_id' => $masterId,
            'channel_type' => $channel_type,
            'in' => [
                'status' => $status
            ]
        ];
        $list = $visaObj->getList($fileds,$where,['id'=>'desc'],[],$page_size);
        $list = $list->toArray();
        $applyIds = array_column($list['data'], 'apply_id');
        $visaIds = array_column($list['data'], 'id');

        $visaRetList = (new FastVisaResult())->getAll(['visa_id', 'inside_opinion', 'out_opinion','reconsideration_status'], ['in'=>['visa_id'=>$visaIds]],['id' => 'asc']);
        $visaResultList = [];
        foreach ($visaRetList as $tempRet) {
            if (in_array($tempRet['reconsideration_status'],[FastVisa::VISA_RECONSIDERATION_STATUS_REFUSE,FastVisa::VISA_RECONSIDERATION_STATUS_PASS])) {
                $visaResultList[$tempRet['visa_id']]['reconsideration_out_opinion'] = $tempRet['out_opinion'];
                $visaResultList[$tempRet['visa_id']]['reconsideration_inside_opinion'] = $tempRet['inside_opinion'];
                continue;
            }
            $visaResultList[$tempRet['visa_id']] = !empty($visaResultList[$tempRet['visa_id']])? array_merge($visaResultList[$tempRet['visa_id']],$tempRet):$tempRet;
        }

        $applyFields = ['carid','finance_status','ver','applyid'];
        $applyList = (new CarHalfApply())->getAll($applyFields, ['in'=>['applyid'=>$applyIds]]);
        $financeStatusDescArr = [];
        $financeStatusArr = config('dict.finance_status');
        foreach ($applyList as  $applyInfo){
            $tempApplyId = $applyInfo['applyid'];
            $financeStatusDescArr[$tempApplyId] = isset($financeStatusArr[$applyInfo['finance_status']]) ? $financeStatusArr[$applyInfo['finance_status']] : $applyInfo['finance_status'];
        }
        $status_dec = config('dict.visa_status');
        //添加 外部意见和内部意见字段
        if($list['data']){
            foreach ($list['data'] as $key => $val){
                $visaId = $val['id'];
                $status = $val['status'];
                $applyId = $val['apply_id'];
                $list['data'][$key]['visa_id'] = $visaId;
                $list['data'][$key]['applyid'] = $applyId;
                $list['data'][$key]['fullname'] = $val['full_name'];
                $list['data'][$key]['carid'] = $val['car_id'];
                $list['data'][$key]['userid'] = $val['user_id'];
                unset($list['data'][$key]['apply_id']);
                unset($list['data'][$key]['full_name']);
                unset($list['data'][$key]['car_id']);
                unset($list['data'][$key]['user_id']);

                $list['data'][$key]['finance_status_desc'] = isset($financeStatusDescArr[$applyId]) ? $financeStatusDescArr[$applyId] : '未查询到数据（数据异常）';
                $list['data'][$key]['out_opinion'] = !empty($visaResultList[$visaId]['out_opinion'])? $visaResultList[$visaId]['out_opinion']:'';
                $list['data'][$key]['inside_opinion'] =  !empty($visaResultList[$visaId]['inside_opinion'])? $visaResultList[$visaId]['inside_opinion']:'';
                $list['data'][$key]['status_dec'] = isset($status_dec[$status]) ? $status_dec[$status]:'';
                $transferStatus = $this->transferStatus($status);
                $list['data'][$key]['status_up_line'] = $transferStatus['status_up_line'];
                $list['data'][$key]['status_quite_line'] = $transferStatus['status_quite_line'];
                $list['data'][$key]['status_change_apply'] = $transferStatus['status_change_apply'];
                $visaExtendObj = new FastVisaExtend();
                $extendRes = $visaExtendObj->getAll(['id','is_reconsideration'],['visa_id' => $visaId]);
                $canUpload = 1;
                if (!empty($extendRes)) {
                    $extendRes = array_column($extendRes,null,'is_reconsideration');
                    #不是拒绝且已经存在一个上传资料 或者 是拒绝已经存在复议资料
                    if (($status != FastVisa::VISA_STATUS_REFUSE && !empty($extendRes[0])) || ($status == FastVisa::VISA_STATUS_REFUSE && !empty($extendRes[1]))) {
                        $canUpload = 0;
                    }
                }
                //第八版新增
                $list['data'][$key]['reconsideration_status'] = !empty($visaResultList[$visaId]['reconsideration_status'])? $visaResultList[$visaId]['reconsideration_status']:0;
                $list['data'][$key]['can_upload'] = $canUpload;
                $list['data'][$key]['reconsideration_out_opinion'] = !empty($visaResultList[$visaId]['reconsideration_out_opinion'])? $visaResultList[$visaId]['reconsideration_out_opinion']:'';
                $list['data'][$key]['reconsideration_inside_opinion'] = !empty($visaResultList[$visaId]['reconsideration_inside_opinion'])? $visaResultList[$visaId]['reconsideration_inside_opinion']:'';
                $list['data'][$key]['reconsideration_desc'] = !empty(FastVisa::$visa_reconsideration_map[$visaResultList[$visaId]['reconsideration_status']])? FastVisa::$visa_reconsideration_map[$visaResultList[$visaId]['reconsideration_status']]:'';
            }
        }
        return [
            'total' => $list['total'],
            'pagesize' => $list['per_page'],
            'current' => $list['current_page'],
            'list' => $list['data']
        ];
    }

    private function transferStatus($status)
    {
        $ret = ['status_up_line'=>0, 'status_quite_line'=>0, 'status_change_apply'=>0];
        if ($status == 1 || $status == 8 || $status == 10) {
            $ret['status_up_line'] = $ret['status_change_apply'] = 1;
        } elseif ($status == 2 || $status == 9 || $status == 11) {
            $ret['status_quite_line'] = 1;
        }
        return $ret;
    }

    //转单
    public function changeApply(Request $request)
    {
        $uri = $request->capture()->getPathInfo();
        $masterId = $request->input('masterid', '');
        try {
            $result = $this->doChangeApply($request);
            $resData = [
                'code' => self::CODE_SUCCESS,
                'message' => self::MSG_SUCCESS,
                'data' => $result
            ];
//            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$resData);
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, []);
        }catch (\Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            $result = ['code' => $code, 'message' => $message];
            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$result);
            if ($code == self::ERROR_CODE)
                return $this->showMsg($code, $message, []);
            else
                return $this->showMsg(self::EXCEPTION_CODE, $message, []);
        }
    }
    private function doChangeApply($request)
    {
        FastGlobal::$retLog = 5;
        $masterId = $request->input('masterid', '');
        $toMasterId = $request->input('tomasterid', '');
        $visaId = $request->input('visa_id', '');

        if(!$masterId || !$toMasterId || !$visaId){
            FastException::throwException(self::MSG_PARAMS, self::ERROR_CODE);
        }

        //查看订单
        $visaModel = new FastVisa();
        $info = $visaModel->getOne(['id','apply_id','inputted_id','status','seat_id','seat_name'],['id'=>$visaId,'master_id'=>$masterId]);
        if(!$info){
            FastException::throwException('未找到订单数据或者订单不在该员工下', self::ERROR_CODE);
        }

        //todo转单目标不存在的时候，要生成一个master账号 todo tanrenzong 这个怎么加
        $toMasterInfo = (new InitialFaceRepository())->getMasterNameByMasterId($toMasterId);
        if (!$toMasterInfo) {
            FastException::throwException('目标员工不存在', self::ERROR_CODE);
        }

        //1未排队 8重新排队可转单
        if(!in_array($info['status'], [FastVisa::VISA_STATUS_NOT_IN_QUEUE, FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT])){
            FastException::throwException('订单正在处理，禁止转单', self::ERROR_CODE);
        }

        DB::connection('mysql.video_visa')->beginTransaction();
        try {
            //更新visa
            $updateData = [
                'seat_id' => 0,
                'seat_name' => '',
                'prev_seat_id' => $info['seat_id'],
                'prev_seat_name' => $info['seat_name'],
                'master_id' => $toMasterId,
                'master_name' => isset($toMasterInfo['mastername']) ? $toMasterInfo['mastername'] : ''
            ];
            $exeResult = $visaModel->updateVisa($updateData, ['id' => $visaId]);
            if (!$exeResult) {
                DB::connection('mysql.video_visa')->rollback();
                FastException::throwException('操作失败2', self::ERROR_CODE);
            }

            //更新transfer
            $transfer = new FastTransferVisa();
            $insertData = [
                'visa_id' => $visaId,
                'master_id' => $masterId,
                'to_master_id' => $toMasterId,
                'create_time' => time(),
            ];
            $add_transfer = $transfer->insert($insertData);
            if(!$add_transfer){
                DB::connection('mysql.video_visa')->rollback();
                FastException::throwException('操作失败', self::ERROR_CODE);
            }
            DB::connection('mysql.video_visa')->commit();
        } catch(\Exception $e) {
            DB::connection('mysql.video_visa')->rollback();
            @Common::sendMail('订单转移异常','参数：masterid:'.$masterId.',tomasterid:'.$toMasterId.',visa_id:'.$visaId.', 错误信息：'.$e->getMessage(), $this->api_error_email);
            FastException::throwException($e->getMessage(), $e->getCode());
        }
        return [];
    }

    /**
     * 退出排队接口
     * @param Request $request
     * @return mixed
     */
    public function quitLine(Request $request){
        $uri = $request->capture()->getPathInfo();
        $masterId = $request->input('masterid', '');
        try {
            $result = $this->doQuitLine($request);
            $resData = [
                'code' => self::CODE_SUCCESS,
                'message' => self::MSG_SUCCESS,
                'data' => $result
            ];
//            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$resData);
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $result);
        }catch (\Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            $result = ['code' => $code, 'message' => $message];
            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$result);
            if ($code == self::ERROR_CODE)
                return $this->showMsg($code, $message, []);
            else
                return $this->showMsg(self::EXCEPTION_CODE, $message, []);
        }
    }

    private function doQuitLine($request)
    {
        FastGlobal::$retLog = 6;
        $masterId = $request->input('masterid', '');
        $visaId = $request->input('visa_id', '');

        if (!$masterId) {
            FastException::throwException(self::MSG_PARAMS, self::ERROR_CODE);
        }

        $visaModel = new FastVisa();
        $visaInfo = $visaModel->getOne(['id','apply_id','inputted_id','status'],['id'=>$visaId,'master_id'=>$masterId]);
        if (!$visaInfo) {
            FastException::throwException('未找到订单数据', self::ERROR_CODE);
        }

        if (in_array($visaInfo['status'], [FastVisa::VISA_STATUS_IN_SEAT, FastVisa::VISA_STATUS_IN_VIDEO])) {
            FastException::throwException('订单正在处理，禁止退出', self::ERROR_CODE);
        }

        if (in_array($visaInfo['status'], FastVisa::$visaFinishedStatusList)) {
            FastException::throwException('订单的面签已经结束', self::ERROR_CODE);
        }

        //已发起排队 和 排队中, 重新排队的订单可退出排队，挂起排队
        if (!in_array($visaInfo['status'], [FastVisa::VISA_STATUS_IN_QUEUEING, FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK,FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT, FastVisa::VISA_STATUS_HANG_QUEUEING])) {
            FastException::throwException('订单未排队或已处理完', self::ERROR_CODE);
        }

        if (FastVisa::lockVisa($visaInfo['id'])) {
            FastException::throwException('该订单已被锁定，禁止退出', self::ERROR_CODE);
        }

        //修改订单状态
        DB::connection('mysql.video_visa')->beginTransaction();
        try {
            $newStatus = FastVisa::VISA_STATUS_NOT_IN_QUEUE;
            if($visaInfo['status'] == FastVisa::VISA_STATUS_HANG_QUEUEING) {
                $newStatus = FastVisa::VISA_STATUS_HANG;
            }
            $exeResult = $visaModel->updateVisaStatus($newStatus, ['id'=>$visaInfo['id']]);
            if (!$exeResult) {
                DB::connection('mysql.video_visa')->rollback();
                FastException::throwException('操作失败', self::ERROR_CODE);
            }
            if(config('common.is_use_new_order_apply')){
                //删除排队队列订单
                $redis_obj = new RedisCommon();
                $sales_key = C('@.common.apply_sale_type_zset_key.'.$visaInfo['sales_type']);
                $redis_obj->zRem($sales_key,$visaInfo['id']);
                $redis_obj->zRem($this->order_key,$visaInfo['id']);
                $fast_visa_rep_obj = new FastVisaRepository();
                $fast_visa_rep_obj->match_master_visa($masterId);
            }

            DB::connection('mysql.video_visa')->commit();
        } catch(\Exception $e) {
            DB::connection('mysql.video_visa')->rollback();
            @Common::sendMail('退出排队异常','参数：masterid:'.$masterId.',visa_id:'.$visaInfo['id'].', 错误信息：'.$e->getMessage(), $this->api_error_email);
            FastException::throwException($e->getMessage(), $e->getCode());
        }

        FastVisa::unLockVisa($visaInfo['id']);

        return [];
    }

    //发起排队
    public function lineUp(Request $request){
        $uri = $request->capture()->getPathInfo();
        $masterId = $request->input('masterid', '');
        try {
            $result = $this->doLineUp($request);
            $resData = [
                'code' => self::CODE_SUCCESS,
                'message' => self::MSG_SUCCESS,
                'data' => $result
            ];
//            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$resData);
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $result);
        }catch (\Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            $result = ['code' => $code, 'message' => $message];
            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$result);
            if ($code == self::ERROR_CODE)
                return $this->showMsg($code, $message, []);
            else
                return $this->showMsg(self::EXCEPTION_CODE, $message, []);
        }
    }

    private function doLineUp($request)
    {
        FastGlobal::$retLog = 7;
        $masterId = $request->input('masterid', '');
        $visaId = $request->input('visa_id', '');

        if(!$masterId) {
            FastException::throwException(self::MSG_PARAMS, self::ERROR_CODE);
        }

        $visaModel = new FastVisa();
        $visaInfo = $visaModel->getOne(['id', 'status', 'master_id', 'seat_id'], ['id'=>$visaId, 'master_id'=>$masterId]);
        if (!$visaInfo) {
            FastException::throwException('未找到订单数据', self::ERROR_CODE);
        }

        if(!in_array($visaInfo['status'],
            [FastVisa::VISA_STATUS_NOT_IN_QUEUE, FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT,FastVisa::VISA_STATUS_HANG])
        ) {
            FastException::throwException('不满足发起排队条件', self::ERROR_CODE);
        }

        //修改订单状态
        $times = time();
        DB::connection('mysql.video_visa')->beginTransaction();
        try {
            //更新visa状态
            $newStatus = FastVisa::VISA_STATUS_IN_QUEUEING;
            if($visaInfo['status'] == FastVisa::VISA_STATUS_HANG) {
                $newStatus = FastVisa::VISA_STATUS_HANG_QUEUEING;
            }
            $exeResult = $visaModel->updateVisaStatus($newStatus, ['id'=>$visaInfo['id']], ['line_up_time'=>time()]);
            if (!$exeResult) {
                DB::connection('mysql.video_visa')->rollback();
                FastException::throwException('发起排队失败3', self::ERROR_CODE);
            }

            //插入visa_log
            $visaLog = [
                'visa_id' => $visaId,
                'master_id' => $masterId,
                'created_at' => date('Y-m-d H:i:s'),
                'queuing_time' => $times,
            ];
            $exeResult = (new FastVisaLog())->insertVisaLog($visaLog);
            if (!$exeResult) {
                FastException::throwException('新增visa_log失败', self::ERROR_CODE);
            }
            if(config('common.is_use_new_order_apply')){
                //订单插入队列
                $FastVisaRepository =  new FastVisaRepository();
                $FastVisaRepository->match_master_visa($masterId);
            }

            DB::connection('mysql.video_visa')->commit();
        } catch(\Exception $e) {
            DB::connection('mysql.video_visa')->rollback();
            @Common::sendMail('发起排队异常','参数：masterid:'.$masterId.',visa_id:'.$visaId.', 错误信息：'.$e->getMessage(), $this->api_error_email);
            FastException::throwException($e->getMessage(), $e->getCode());
        }

        //计算等待时间
        $data['wait_time'] = (new ApplyRepository())->getApplyWaitTime($visaInfo, $times);
        return $data;
    }

    //账号返回接口
    public function getAccountInfo(Request $request){
        $uri = $request->capture()->getPathInfo();
        $masterId = $request->input('masterid', '');
        try {
            $result = $this->doGetAccountInfo($request);
            $resData = [
                'code' => self::CODE_SUCCESS,
                'message' => self::MSG_SUCCESS,
                'data' => $result
            ];
//            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$resData);
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $result);
        }catch (\Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            $result = ['code' => $code, 'message' => $message];
            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$result);
            if ($code == self::ERROR_CODE)
                return $this->showMsg($code, $message, []);
            else
                return $this->showMsg(self::EXCEPTION_CODE, $message, []);
        }
    }

    private function doGetAccountInfo($request)
    {
        FastGlobal::$retLog = 8;
        $masterid = $request->input('masterid', '');
        $channel_type = $request->input('channel_type', 1);

        //默认为二手车
        if (!in_array($channel_type, [1,2])) $channel_type = 1;

        if(!$masterid || !in_array($channel_type, [1,2])){
            FastException::throwException(self::MSG_PARAMS.':'.$channel_type, self::EXCEPTION_CODE);
        }

        //获取账号信息
        $im_account = new ImAccount();
        $im_rbac_master = new ImRbacMaster();
        $rbsc_master = $im_rbac_master->getOne(['im_account_id','mastername'],['masterid'=>$masterid,'channel_type'=>$channel_type]);
        if($rbsc_master){
            $info = $im_account->getOne(['accid','nickname','token'],['id'=>$rbsc_master['im_account_id']]);
            $ret = [
                'masterid' => $masterid,
                'mastername' => $rbsc_master['mastername'],
                'accid' => $info['accid'],
                'nickname' => $info['nickname'],
                'token' => $info['token'],
                'userSig' => (new WebRTCSigApi('fast'))->genUserSig($info['accid']),
            ];
            return $ret;
        }
        //不存在，则注册
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
            FastException::throwException('未找到销售人员信息', self::EXCEPTION_CODE);
        }
        $accid = $master_info['mastername'] . '_' . $this->env . '_' . $accid_pre;
        //入库
        DB::connection('mysql.video_visa')->beginTransaction();
        try {

            $times = time();
            $im_params = [
                'accid' => $accid,
                'nickname' => $master_info['fullname'],
                'token' => '',
                'update_time' => $times,
                'create_time' => $times,
            ];

            $im_account_id = $im_account->insertGetId($im_params);
            if(!$im_account_id){
                FastException::throwException('添加失败1', self::ERROR_CODE);
            }

            $master_params = [
                'masterid' => $masterid,
                'mastername' => $master_info['mastername'],
                'channel_type' => $channel_type,
                'email' => $master_info['email'],
                'mobile' => $master_info['mobile'],
                'fullname' => $master_info['fullname'],
                'im_account_id' => $im_account_id,
                'create_time' => $times,
            ];
            $im_rbac_master = $im_rbac_master->insert($master_params);
            if(!$im_rbac_master){
                FastException::throwException('添加失败2', self::ERROR_CODE);
            }
            DB::connection('mysql.video_visa')->commit();

            //返回参数
            $ret = [
                'masterid' => $masterid,
                'mastername' => $master_info['mastername'],
                'accid' => $im_params['accid'],
                'nickname' => $im_params['nickname'],
                'token' => $im_params['token'],
                'userSig' => (new WebRTCSigApi('fast'))->genUserSig($accid),
            ];
            return $ret;

        } catch(\Exception $e) {
            DB::connection('mysql.video_visa')->rollback();
            @Common::sendMail('添加网易账号信息失败','错误信息：'.$e->getMessage(), $this->api_error_email);
            FastException::throwException($e->getMessage(), $e->getCode());
        }
    }

    //图片上传
    public function data_supplement(Request $request){
        $uri = $request->capture()->getPathInfo();
        $masterId = $request->input('masterid', '');
        //入库
        DB::connection('mysql.video_visa')->beginTransaction();
        try {
            $result = $this->doUpload($request);
            DB::connection('mysql.video_visa')->commit();
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $result);
        }catch (\Exception $e) {
            DB::connection('mysql.video_visa')->rollback();
            $code = $e->getCode();
            $message = $e->getMessage();
            $result = ['code' => $code, 'message' => $message];
            (new AsyncInsertRepository())->pushCjbLog($masterId, $uri,$result);
            if ($code == self::ERROR_CODE)
                return $this->showMsg($code, $message, []);
            else
                return $this->showMsg(self::EXCEPTION_CODE, $message, []);
        }
    }

    private function doUpload($request) {
        FastGlobal::$retLog = 11;
        $masterid = $request->input('masterid', '');
        $id = intval($request->input('id', ''));
        $bank_flow = trim($request->input('bank_flow', ''));
        $proof_of_assets = trim($request->input('proof_of_assets', ''));
        $other = trim($request->input('other', ''));
        $remark = strval(trim($request->input('remark', '')));
        $is_reconsideration= $request->input('is_reconsideration', 0);
        if (!$is_reconsideration || !$masterid || !$id || (!$bank_flow && !$proof_of_assets && !$other && !$remark )) {
            FastException::throwException(self::MSG_PARAMS, self::ERROR_CODE);
        }
        $data_supplement = [
            'bank_flow' => [],//银行流水
            'proof_of_assets' => [],//资产证明
            'other' => [],//其他图片
        ];
        foreach ($data_supplement as $key => $value) {
            $temp = $$key;
            if ($temp) {
                $data_supplement[$key] = explode(',',$temp);
            }else{
                unset($data_supplement[$key]);
            }
        }
        if (empty($data_supplement) && !$remark) {
            FastException::throwException(self::MSG_PARAMS, self::ERROR_CODE);
        }
        $extend = [
            'visa_id' => $id,
            'data' => json_encode_new($data_supplement),
            'remark' => $remark,
            'is_reconsideration' => $is_reconsideration,
            'create_at' => time(),
            'type' => 0,
        ];
        $visaObj = new FastVisa();
        $findRes = $visaObj->getOne(['status','reconsideration_status'],['id' => $id]);
        $visaExtendObj = new FastVisaExtend();
        $extendPareRes = $visaExtendObj->getOne(['id'],['visa_id' => $id,'is_reconsideration' => 0]);
        $extendHasRes = $visaExtendObj->getOne(['id'],['visa_id' => $id,'is_reconsideration' => FastVisa::VISA_RECONSIDERATION_STATUS_NOT]);
        #已经上传了一次复议资料或者 已经上传了一次资料,第二次上传但是is_reconsideration为0
        if ($extendHasRes || ($extendPareRes && $is_reconsideration !=  FastVisa::VISA_RECONSIDERATION_STATUS_NOT)) {
            FastException::throwException(self::MSG_PARAMS, self::ERROR_CODE);
        }
        #非复议,已经上传了一次资料
        if ($extendPareRes && $is_reconsideration == 0) {
            FastException::throwException(self::MSG_FAIL, self::ERROR_CODE);
        }
        #状态不是拒绝且is_reconsideration是1
        if ($findRes['status'] != FastVisa::VISA_STATUS_REFUSE && $is_reconsideration == FastVisa::VISA_RECONSIDERATION_STATUS_NOT ) {
            FastException::throwException(self::MSG_FAIL, self::ERROR_CODE);
        }
        $ins_res = $visaExtendObj->insert($extend);
        if ($ins_res) {
            #是拒绝状态
            if ($findRes['status'] == FastVisa::VISA_STATUS_REFUSE) {
                $updateRes = $visaObj->updateBy(['reconsideration_status'=> FastVisa::VISA_RECONSIDERATION_STATUS_CAN],['id' => $id]);
                if ($updateRes) {
                    $logRes = (new FastVisaLog())->insertVisaLog([
                        'visa_id' => $id,
                        'master_id' => $masterid,
                        'queuing_time' => time(),
                        'reconsideration_status' => FastVisa::VISA_RECONSIDERATION_STATUS_CAN,
                        'created_at' => date('Y-m-d H:i:s',time())
                    ]);
                    if (!$logRes) {
                        FastException::throwException(self::MSG_FAIL, self::ERROR_CODE);
                    }
                }
            }
        }else{
            FastException::throwException(self::MSG_FAIL, self::ERROR_CODE);
        }
        return ['id' => $id];
    }
}