<?php
namespace App\Http\Controllers\Api;


use App\Fast\FastException;
use App\Fast\FastGlobal;
use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Library\RedisCommon;
use App\Models\VideoVisa\FastVisa;
use App\Models\XinCredit\CarHalfApplyCredit;
use App\Models\XinFinance\CarHalfService;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\FastVisaResult;
use App\Repositories\Api\InitialFaceRepository;
use App\Repositories\Async\AsyncInsertRepository;
use App\Repositories\FastVisaRepository;
use App\XinApi\AptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InitialFaceController extends BaseController
{
    private $common;
    private $initial_face_rep;
    const INIT_LOCK = 'fast_youxinjinrong_init_lock';

    function __construct()
    {
        parent::__construct();
        $this->common = new Common();
        $this->initial_face_rep = new InitialFaceRepository();
    }

    //初始化智能面签数据
    public function initialData(Request $request)
    {
        $uri = $request->capture()->getPathInfo();
        $applyId = intval($request->input('applyid',0));
        try {
            $result = $this->doInitialData($request);
            $resData = [
                'code' => self::CODE_SUCCESS,
                'message' => '该单操作成功!',
                'data' => $result
            ];
//            (new AsyncInsertRepository())->pushErpLog($applyId, $uri,$resData);
            return $this->showMsg(self::CODE_SUCCESS, '该单操作成功!', $result);
        }catch (\Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            $result = ['code' => $code, 'message' => $message];
            (new AsyncInsertRepository())->pushErpLog($applyId, $uri,$result);
            //重复操作直接返回成功
            if($code==8){
                $code = self::CODE_SUCCESS;
            }
            return $this->errorAlarm($code, $applyId, $e->getMessage());
        }
    }

    private function doInitialData($request)
    {
        FastGlobal::$retLog = 1;
        $applyId = intval($request->input('applyid',0));
        $webank_apply_data_id = intval($request->input('webank_apply_data_id',0));

        //获取业务类型
        if(empty($applyId)) FastException::throwException(self::MSG_PARAMS, self::CODE_FAIL);

        try {
            //禁止重复初始化，加redis为了防止主从延迟
            $redis = new RedisCommon();
            $lockKey = self::INIT_LOCK . $applyId;
            if ($redis->get($lockKey) !== false || Common::redis_lock($lockKey)) {
                FastException::throwException('请求太过频繁', self::CODE_FAIL);
            }
            //visa表新增
            DB::connection('mysql.video_visa')->beginTransaction();
            $this->insertVisaInfo($applyId,$webank_apply_data_id);
            DB::connection('mysql.video_visa')->commit();
            $redis->setex($lockKey, $applyId, 86400);
            Common::redis_lock($lockKey,true);
            return [];
        } catch (\Exception $p) {
            Common::redis_lock($lockKey,true);
            DB::connection('mysql.video_visa')->rollback();
            throw new \Exception($p->getMessage(),$p->getCode());
        }
    }

    /**
     * 新增visa
     * @param int $applyId 放款id
     * @param $applyId
     * @param $webank_apply_data_id
     * @return mixed
     * @throws FastException
     */
    private function insertVisaInfo($applyId, $webank_apply_data_id=0)
    {
        $carHalfApply = $this->initial_face_rep->getCarHalfApply($applyId);
        if(empty($carHalfApply)) FastException::throwException('car_half_apply没有查到该订单信息', 2);

        $car = $this->initial_face_rep->getCar($carHalfApply['carid']);
        if(empty($car)) FastException::throwException('没有查到该订单车辆信息', 3);
        $vin = $this->initial_face_rep->getVin($carHalfApply['carid']);
        $xinOrder = $this->initial_face_rep->getXinOrder($applyId);
        if(empty($xinOrder)) FastException::throwException('没有查到该订单inputted_id信息', 4);
        $carHalfService = $this->initial_face_rep->getCarHalfService($carHalfApply['carid'],$carHalfApply['userid']);
        //全国直购
        if(!empty($carHalfService) && ($carHalfService['purchase_type'] == CarHalfService::PURCHASE_TYP_DIRECTLY)){
            $masterInfo = AptService::getDealerIdByCarIdDIR($carHalfApply['carid']);
        }else{
            $masterInfo = AptService::getDealerIdByCarId($carHalfApply['carid']);
        }
        if (empty($masterInfo) || !isset($masterInfo['salerid']) || !isset($masterInfo['mastername'])) {
            FastException::throwException('获取销售信息失败', 6);
        }
        $car_half_service_model = new CarHalfService();
        $erp_credit_status = InitialFaceRepository::credit_info($applyId);
        $sales_type = $car_half_service_model->get_channel_type($carHalfApply['carid'],$carHalfApply['userid']);

        if(empty($webank_apply_id)){
            $webank_id = $this->initial_face_rep->getWebankApplyDataId($applyId);
            if($webank_id){
                $webank_apply_data_id = $webank_id['webank_apply_data_id'];
            }
        }

        $credit_id = $this->getCreditId($carHalfApply['userid'],$webank_apply_data_id);
        //新增visa
        $insert_data = [
            'apply_id' => $applyId,
            'status' => FastVisa::VISA_STATUS_NOT_IN_QUEUE,
            'erp_credit_status' => !empty($erp_credit_status[$applyId])? $erp_credit_status[$applyId]:0,
            'sales_type' => $sales_type,
            'user_id' => $carHalfApply['userid'],
            'inputted_id' => empty($xinOrder['inputted_id']) ? '' : $xinOrder['inputted_id'],
            'init_master_id' => isset($masterInfo['salerid']) ? $masterInfo['salerid'] : '',
            'master_id' => isset($masterInfo['salerid']) ? $masterInfo['salerid'] : '',
            'master_name' => isset($masterInfo['mastername']) ? $masterInfo['mastername'] : '',
            'full_name' => $carHalfApply['fullname'],
            'mobile' => $carHalfApply['mobile'],
            'id_card_num' => isset($carHalfApply['id_card_num']) ? $carHalfApply['id_card_num'] : '',
            'vin' => $vin,
            'car_id' => $carHalfApply['carid'],
            'car_name' => empty($car['carname']) ? '' : $car['carname'],
            'channel' => $carHalfApply['fund_channel'],
            'channel_type' => $carHalfApply['channel_type'],
            'business_type' => $carHalfApply['product_stcode'],
            'car_city_id' => $carHalfApply['car_cityid'],
            'risk_start_name' => $carHalfApply['risk_start_name'],
            'risk_time' => strtotime($carHalfApply['updated_at']),
            'created_at' => date('Y-m-d H:i:s'),
            'credit_apply_id' => !empty($credit_id)? $credit_id:0,
        ];
        $visaId = $this->initial_face_rep->insertVisaData($insert_data);
        if (!$visaId) {
            FastException::throwException('新增visa失败', 9);
        }
    }
    //获取面签结果
    public function faceResult(Request $request){
        $uri = $request->capture()->getPathInfo();
        $applyId = $request->input('applyid','');
        try {
            $result = $this->doGetFaceResult($request);
            $resData = [
                'code' => self::CODE_SUCCESS,
                'message' => self::MSG_SUCCESS,
                'data' => $result
            ];
//            (new AsyncInsertRepository())->pushErpLog($applyId, $uri,$resData);
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $result);
        }catch (FastException $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            $result = ['code' => $code, 'message' => $message, 'data' => $e->getExtra()];
            (new AsyncInsertRepository())->pushErpLog($applyId, $uri,$result);

            // 根据仁宗的注释调整代码 此处特殊处理，不报错，返回数据为空
            return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS,$e->getExtra());
        }catch (\Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            $result = ['code' => $code, 'message' => $message];
            (new AsyncInsertRepository())->pushErpLog($applyId, $uri,$result);
            return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS);
        }
    }

    private function doGetFaceResult($request)
    {
        FastGlobal::$retLog = 2;
        $applyId = $request->input('applyid','');
        if(empty($applyId))  FastException::throwException(self::MSG_PARAMS, self::CODE_FAIL);
        return $this->initial_face_rep->getFaceResult($applyId);
    }


    public function initialDataOld(Request $request){
        FastGlobal::$retLog = 1;
        $applyid = $request->input('applyid','');
        //获取业务类型
        if(empty($applyid)){
            return $this->showMsg(self::CODE_FAIL);
        }
        //$business_type = $this->common->getAllProductScheme();
        DB::connection('mysql.video_visa')->beginTransaction();
        try {
            $car_half_apply = $this->initial_face_rep->getCarHalfApply($applyid);
            if(empty($car_half_apply)){
                DB::connection('mysql.video_visa')->rollback();
                return $this->errorAlarm(2,$applyid, 'car_half_apply没有查到该订单信息');
            }
            $car = $this->initial_face_rep->getCar($car_half_apply['carid']);
            if(empty($car)){
                DB::connection('mysql.video_visa')->rollback();
                return $this->errorAlarm(3,$applyid, '没有查到该订单车辆信息');
            }
            $xin_order = $this->initial_face_rep->getXinOrder($applyid);
            if(empty($xin_order)){
                DB::connection('mysql.video_visa')->rollback();
                return $this->errorAlarm(4,$applyid, '没有查到该订单inputted_id信息');
            }

            $masterid = AptService::getDealerIdByCarId($car_half_apply['carid']);
            if (empty($masterid)) {
                DB::connection('mysql.video_visa')->rollback();
                return $this->errorAlarm(6, $applyid, '获取销售id失败');
            }

            $insert_data = [];
            if($car_half_apply){
                $insert_data = [
                    'applyid' => $applyid,
                    'userid' => $car_half_apply['userid'],
                    'inputted_id' => empty($xin_order['inputted_id']) ? '' : $xin_order['inputted_id'],
                    'masterid' => $masterid,
                    'fullname' => $car_half_apply['fullname'],
                    'mobile' => $car_half_apply['mobile'],
                    'carid' => $car_half_apply['carid'],
                    'channel' => $car_half_apply['fund_channel'],
                    'channel_type' => $car_half_apply['channel_type'],
                    'business_type' => $car_half_apply['product_stcode'],
                    'risk_at' => $car_half_apply['updated_at'],
                    'car_cityid' => $car_half_apply['car_cityid'],
                    'risk_start_name' => $car_half_apply['risk_start_name'],
                    'car_name' => empty($car['carname']) ? '' : $car['carname'],
                    'create_time' => time(),
                    'update_time' => time()
                ];
            }
            //根据applyid进行排重
            $existId = $this->initial_face_rep->visa_pool->getOne(['id'], ['applyid'=>$applyid]);
            if ($existId) {
                DB::connection('mysql.video_visa')->rollback();
                return $this->errorAlarm(7, $applyid, '请勿重复发起面签');
            }

            $this->initial_face_rep->insertFaceData($insert_data);
            $this->initial_face_rep->insertRemarkData($insert_data);
            DB::connection('mysql.video_visa')->commit();
            return $this->showMsg(self::CODE_SUCCESS,'该单操作成功！');
        } catch (\Exception $e) {
            $title = $_SERVER['SITE_ENV'] == 'testing' ? '【测试机】中央面签报错 - ' : '中央面签报错 - ';
            DB::connection('mysql.video_visa')->rollback();
            $this->common->sendMail($title.'初始化面签报警邮件', $applyid.'没有查到该订单信息',array() );
            return $this->errorAlarm(5,$applyid, '插入失败！');
        }
    }

    public function errorAlarm($type,$applyid,$message){
        $title = $_SERVER['SITE_ENV'] == 'testing' ? '【测试机】中央面签报错 - ' : '中央面签报错 - ';
        $mail_to = config('mail.developer');
        $this->common->sendMail($title.'初始化面签报警邮件', $applyid.$message,$mail_to );
        $this->initial_face_rep->insertInitialFaceLog($type, ['applyid' =>$applyid,'data'=>$message]);
        return $this->showMsg($type,$message);
    }

    //获取智能面签结果 1、审核通过；2、审核拒绝；3、跳过面签；4、待面签；
    public function faceResultOld(Request $request){
        FastGlobal::$retLog = 2;
        /*$data = $request->input('data','');
        $params = json_decode($data, true);
        $applyid = $params['applyid'];*/
        $applyid = $request->input('applyid','');
        if(empty($applyid)){
            return $this->showMsg(self::CODE_FAIL);
        }

        //获取面签信息
        $remark_data = $this->initial_face_rep->getRemarkData($applyid);
        if(empty($remark_data['applyid']) || empty($remark_data['seat_id'])){
            $data = ['inside_opinion' => '','out_opinion' => '','status' => 4];
            return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS,$data);
        }

        //获取面签结果信息
        $face_result = $this->initial_face_rep->getRemarkAttach($remark_data);
        if(empty($face_result)){
            $data = ['inside_opinion' => '','out_opinion' => '','status' => 4];
            return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS,$data);
        }
        if($face_result['status'] == 5){
            $data = ['inside_opinion' => $face_result['inside_opinion'],'out_opinion' => $face_result['out_opinion'],'status' => 1];
            return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS,$data);
        }elseif ($face_result['status'] == 6){
            $data = ['inside_opinion' => $face_result['inside_opinion'],'out_opinion' => $face_result['out_opinion'],'status' => 2];
            return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS,$data);
        }elseif ($face_result['status'] == 7){
            $data = ['inside_opinion' => $face_result['inside_opinion'],'out_opinion' => $face_result['out_opinion'],'status' => 3];
            return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS,$data);
        }else{
            $data = ['inside_opinion' => $face_result['inside_opinion'],'out_opinion' => $face_result['out_opinion'],'status' => 4];
            return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS,$data);
        }
    }
    private function getCreditId($uid,$webank_apply_data_id) {
        if (empty($uid) || empty($webank_apply_data_id)) {
            return 0;
        }
        $conds = array(
            'userid' => $uid,
            'webank_apply_data_id' => $webank_apply_data_id,
            'carid' => 0,
            'rent_type' => 2//只查直租
        );
        $result = CarHalfApplyCredit::where($conds)->select("applyid")->orderBy("applyid","desc")->first();
        if(!empty($result)) {
           return intval($result['applyid']);
        }
        return 0;
    }
}
