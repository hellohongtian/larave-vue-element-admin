<?php
namespace App\Repositories\Api;


use App\Fast\FastException;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\InitialFaceLog;
use App\Models\VideoVisa\VisaPool;
use App\Models\VideoVisa\VisaRemark;
use App\Models\VideoVisa\VisaRemarkAttach;
use App\Models\Xin\Car;
use App\Models\Xin\CarDetail;
use App\Models\Xin\CarHalfApply;
use App\Models\XinCredit\CarHalfApplyCredit;
use App\Models\Xin\RbacMaster;
use App\Models\XinFinance\CarHalfService;
use App\Models\XinFinance\CarLoanOrder;
use App\Models\XinPay\XinOrder;
use Illuminate\Support\Facades\DB;
use App\Repositories\CommonRepository;

class InitialFaceRepository
{
    public $car_half_apply;
    public $car;
    public $car_detail;
    public $xin_order;
    public $visa_remark = null;
    public $visa_pool = null;
    public $initial_face_log = null;
    public $visa_remark_attach = null;
    public $fastVisa = null;
    public $car_half_service = null;
    public $car_loan_order = null;

    public function __construct()
    {
        $this->car_half_apply = new CarHalfApply();
        $this->car = new Car();
        $this->xin_order = new XinOrder();
        $this->initial_face_log = new InitialFaceLog();
        $this->visa_remark = new VisaRemark();
        $this->visa_pool = new VisaPool();
        $this->visa_remark_attach = new VisaRemarkAttach();
        $this->fastVisa = new FastVisa();
        $this->car_half_service = new CarHalfService();
        $this->car_detail = new CarDetail();
        $this->car_loan_order = new CarLoanOrder();
    }

    //获取car_half_apply值
    public function getCarHalfApply($applyid){
        $car_half_apply = $this->car_half_apply->getOne(['userid','id_card_num','fullname','mobile','carid','fund_channel','channel_type','product_stcode','risk_start_name','car_cityid','updated_at'],['applyid' => $applyid]);
        return $car_half_apply;
    }
    //获取car_half_service值
    public function getCarHalfService($carid,$userid){
        $car_half_service = $this->car_half_service->getOne(['purchase_type'],['carid' => $carid,'userid'=>$userid],['id'=>'desc']);
        return $car_half_service;
    }

    //获取car值
    public function getCar($carid=0){
        $car = $this->car->getOne(['carname'],['carid' => $carid]);
        return $car;
    }

    //获取vin值
    public function getVin($carid=0){
        $car = $this->car_detail->getOne(['vin'],['carid' => $carid]);
        return !empty($car)? $car['vin']:'';
    }

    //获取xin_order(inputted_id)值
    public function getXinOrder($applyid){
        $xin_order = $this->xin_order->getOne(['inputted_id'],['applyid' => $applyid]);
        return $xin_order;
    }

    public function insertInitialFaceLog($type,$data){
        $insert_data = ['type'=>$type,'text'=>json_encode($data)];
        $this->initial_face_log->insert($insert_data);
    }

    public function getWebankApplyDataId($applyid){
        $webank = $this->car_loan_order->getOne(['webank_apply_data_id'],['applyid' => $applyid]);
        return $webank;
    }
    /**
     * 新增visa
     * 项目中插入visa表的唯一入口，不允许新增别的方法单独插入！
     * @param $insertData
     * @return mixed
     * @throws FastException
     */
    public function insertVisaData($insertData)
    {
        if (!isset($insertData['apply_id']) || empty($insertData['apply_id'])) {
            throw new FastException('apply_id不允许为空', 7);
        }

        //根据applyid进行排重
        $isApplyIdExist = $this->fastVisa->getOne(['id'], ['apply_id'=>$insertData['apply_id']]);
        if ($isApplyIdExist) {
            FastException::throwException('请勿重复发起面签,apply_id:' . $insertData['apply_id'], 8);
        }

        //插入时间
        $insertData['created_at'] = isset($insertData['created_at']) ? $insertData['created_at'] : date('Y-m-d H:i:s');
        $insertData['updated_at'] = isset($insertData['updated_at']) ? $insertData['updated_at'] : date('Y-m-d H:i:s');

        $exeResult = (new FastVisa())->insertGetId($insertData);
        if (!$exeResult) {
            FastException::throwException('插入失败');
        }

        return $exeResult;
    }

    //visa_result获取结果
    public function getFaceResult($applyId)
    {
        $visaInfo = (new FastVisa())->getOne(['id', 'status','seat_id', 'master_id','visa_time'], ['apply_id' => $applyId], ['id'=>'desc']);
        if(empty($visaInfo) || empty($visaInfo['seat_id']) || empty($visaInfo['master_id'])){
            FastException::throwException('empty', 2, 0, ['inside_opinion' => '','out_opinion' => '','status' => -1,'need_verify'=>0,'visa_time'=>'']);
        }
        $visaResult = (new FastVisaResult())->getVisaRet($visaInfo['id'], $visaInfo['seat_id'], $visaInfo['master_id']);
        if($visaInfo['status'] == 5){
            $status = 1;
        }elseif ($visaInfo['status'] == 6){
            $status = 2;
        }elseif ($visaInfo['status'] == 7){
            $status = 3;
        }else{
            $status = 4;
        }
        $result = [
            'inside_opinion' => isset($visaResult['inside_opinion']) ? $visaResult['inside_opinion'] : '',
            'out_opinion' => isset($visaResult['out_opinion']) ? $visaResult['out_opinion'] : '',
            'status' => $status,
            'need_verify' => !empty($visaResult['need_verify'])? 1:0,
            'visa_time' => !empty($visaInfo['visa_time'])? date("Y-m-d H:i:s",$visaInfo['visa_time']):'',
        ];
        return $result;
    }

    //初始化visa_pool表
    public function insertFaceData($insert_data){
        return $this->visa_pool->insert($insert_data);
    }

    //初始化visa_remark表
    public function insertRemarkData($insert_data){
        return $this->visa_remark->insert($insert_data);
    }

    //获取面签信息
    public function getRemarkData($applyid){
        return $this->visa_remark->getOne(['applyid','seat_id'],['applyid' => $applyid]);
    }

    //获取面签结果信息
    public function getRemarkAttach($remark_data){
        return $this->visa_remark_attach->getOne(['status','inside_opinion','out_opinion'],['applyid' => $remark_data['applyid'],'seat_id' => $remark_data['seat_id']]);
    }

    //通过masterid获取mastername
    public function getMasterNameByMasterId($masterId)
    {
        $rbacMasterModel = new RbacMaster();
        return $rbacMasterModel->getOne(['*'], ['masterid'=>$masterId]);
    }


    /**
     * 信审信息，判断是人工信审还是机器信审
     * @param array $applyid
     * @return array 0未知 1人工 2机器
     */
    public  static  function credit_info($applyid)
    {
        if (!is_array($applyid)) {
            $applyid = array($applyid);
        }
        $applyid_str = implode(',',$applyid);
        //订单费用明细数据
        $order_fee = \App\XinApi\ErpApi::getCarFee($applyid_str);
        if(empty($order_fee)){
            return [];
        }
        foreach ($order_fee as $key => $value) {
            $webank_apply_data_id = isset($value['webank_apply_data_id'])? (int)$value['webank_apply_data_id']:0;
            $rent_type = isset($value['rent_type'])? (int)$value['rent_type']:0;
            if(empty($webank_apply_data_id) || empty($rent_type) ) {
                $result[$key] = 0;
                continue;
            }
            $car_half_apply = new CarHalfApplyCredit();
            $info = $car_half_apply->getOne(
                ['status','cp_status','cp_userid','dispose_userid'],
                [
                    'webank_apply_data_id'=>$webank_apply_data_id,
                    'rent_type' => $rent_type,
                    'carid' => 0
                ],
                ['webank_apply_data_id'=>'desc']);

            if(isset($info['status'])  && isset($info['cp_status']) && isset($info['cp_userid']) && isset($info['dispose_userid'])){
                if(
                    ($info['status'] == 2 && $info['cp_status'] == -10 && in_array($info['cp_userid'],[4438,4744])) ||
                    ($info['status'] == 2 && $info['cp_status'] == 10  && in_array($info['dispose_userid'],[4438,4744])) ||
                    ($info['status'] == 2 && $info['cp_status'] == -60  && $info['cp_userid'] == 4438)
                )
                {
                    $result[$key] = 2;

                }
                $result[$key] = 1;
            }else{
                $result[$key] = 1;
            }
        }
        return $result;


    }
}