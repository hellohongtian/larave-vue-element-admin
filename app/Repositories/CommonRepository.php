<?php
namespace App\Repositories;


use App\Models\RiskStat\DecisionData;
use App\Models\Xin\CarHalfApply;
use App\Models\XinCredit\CarHalfApplyCredit;
use App\Models\Xin\CarHalfRemark;
use App\Models\Xin\ErpMaster;
use App\Models\Xin\RbacMaster;
use App\Models\XinFinance\CarLoanOrder;
use App\Models\XinFinance\CarLoanOrderMore;
use App\Models\XinFinance\CarBeyondOrder;
use App\Models\XinFinance\LoaninSignMess;
use App\Models\XinCredit\PersonCredit;
use App\Models\Xin\DiscountApply;
use App\Models\XinCredit\PersonCreditResult;
use App\Models\Xin\Bank;
use App\Models\Xin\Car;
use App\Models\Xin\CarDetail;
use App\Models\Xin\CarHalfDetail;
use App\Models\Xin\CkCarTask;
use App\Models\Xin\CollectCar;
use App\Models\Xin\CxBrand;
use App\Models\Xin\CxMode;
use App\Models\Xin\CxSeries;
use App\Models\Xin\Dealer;
use App\Models\XinCredit\WebankApplyData;
use App\Models\NewCar\CxMake;
use App\Models\NewCar\CxModeFinance;
use App\Models\NewCar\CxSeries as NewcarCxSeries;
use App\Models\NewCar\CxBrand as NewcarCxBrand;
use App\Models\NewCar\CxMode as NewcarCxMode;
use  App\Models\Xin\BankPay;
use App\Models\NewCar\DealerBank;
use App\Models\NewCar\Dealer as NewCarDealer;
use App\XinApi\CreditApi;
use App\XinApi\ErpApi;
use App\XinApi\FinanceApi;
use App\Library\HttpRequest;
use DB;

class CommonRepository
{

    /*****model start***********/

    //付一半订单表
    public $car_loan_model = null;

    //付一半订单附属表
    public $loan_more_model = null;

    //付一半订单表
    public $car_half_apply_model = null;

    //信审表
    public $car_half_apply_credit_model = null;

    //个人征信主表
    public $person_credit = null;

    //个人征信结果表
    public $credit_ret_model = null;

    //新车超融订单表
    public $car_beyond = null;

    //贴息综合表
    public $discount_apply_model = null;

    //新车经销商(New_Car)
    public $newcar_dealer_model = null;

    //经销商(
    public $dealer_model = null;

    //银行
    public $bank_model = null;

    //银行经销
    public $bank_dealer_model = null;

    //car
    public $car_model = null;

    //CxBrand
    public $cxbrand_model = null;

    //newcar CxBrand
    public $newcar_cxbrand_model = null;

    //CxSeries
    public $cxseries_model = null;

    //newcar CxSeries
    public $newcar_cxseries_model = null;

    //CarDetail
    public $car_detail_model = null;

    //CxMode
    public $cxmode_model = null;

    //newcar CxMode
    public $newcar_cxmode_model = null;

    //CarHalfDetail
    public $carhalf_detail_model = null;

    //collect_car 采集车源表
    public $collect_car_model = null;

    //ck_car_task 检测报告表
    public $ckcar_task_model = null;

    //webank_apply_data 信审四要素提交表
    public $webank_apply_data_model = null;

    //CxMake 车辆厂商表
    public $cxmake_model = null;

    //cx_mode_finance 车型金融属性表
    public $cxmode_finance_model = null;

    //订单收款记录表
    public $bank_pay_model = null;

    //决策引擎信审表
    public $decision_data_model = null;

    //补录信息
    public $loanin_sign_mess_model = null;

    /*****model end***********/



    /*****数据信息 start***********/
    //applyid
    public $applyid = null;

    //信审apply_id
    public $credit_apply_id = null;

    //付一半订单附属表
    public $car_loan_order_more_info = null;

    //付一半订单表
    public $car_loan_order_info = null;

    //付一半订单费用明细表
    public $free_detail_info = null;

    //外部信息
    public $outside_info = null;

    //新车超融订单信息
    public $car_beyond_info = null;

    //贴息综合信息
    public $discount_apply_info = null;

    //信审结果
    public $credit_ret_info = null;

    //经销商信息
    public $dealer_info = null;

    //经销商信息(new_car)
    public $newcar_dealer_info = null;

    //信审结果
    public $bank_info = null;

    //经销商银行信息
    public $bank_dealer_info = null;

    //car
    public $car_info = null;

    //CxBrand
    public $cxbrand_info = null;

    //newcar CxBrand
    public $newcar_cxbrand_info = null;

    //CxSeries
    public $cxseries_info = null;

    //newcar CxSeries
    public $newcar_cxseries_info = null;

    //CarDetail
    public $car_detail_info = null;

    //CxMode
    public $cxmode_info = null;

    //newcar CxMode
    public $newcar_cxmode_info = null;

    //CarHalfDetail
    public $carhalf_detail_info = null;

    //collect_car 采集车源表
    public $collect_car_info = null;

    //ck_car_task 检测报告表
    public $ckcar_task_info = null;

    //webank_apply_data 信审四要素提交表
    public $webank_apply_data_info = null;

    //CxMake 车辆厂商表
    public $cxmake_info = null;

    //cx_mode_finance 车型金融属性表
    public $cxmode_finance_info = null;


    /*****数据信息 end***********/



    /****对外数据******/
    //订单信息
    public $apply_info = null;

    //用户信审信息
    public $user_info = null;

    //首付款核销明细区
    public $pay_ment_info = null;

    //首付款核销明细区(超融)
    public $pay_ment_info_beyond = null;

    //合同数据
    public $contract_info = null;

    //超融明细
    public $beyond_info = null;

    //贴息信息
    public $discount_info = null;

    //经销商信息
    public $dealer_ret = null;

    //车辆信息
    public $out_carinfo = null;

    //Vin对应的车型
    public $vin_car_info = null;

    //刷卡总笔数
    public $paylog_count = null;

    //补充信息
    public $supplement_info = null;

    //面签补录信息
    public $loanin_sign_mess_info = null;

    //信审结果
    public $decision_info = null;

    //审批记录
    public $remark_info = null;

    /****对外数据******/


    const CODE_SUCCESS = 1;
    const CODE_FAIL = -1;
    const CODE_UPDATE = 2;

    const MSG_SUCCESS = '操作成功';
    const MSG_FAIL = '操作失败';
    const MSG_PARAMS = '参数传递错误';


    public function __construct()
    {
        $this->car_loan_model = new CarLoanOrder();
        $this->loan_more_model = new CarLoanOrderMore();
        $this->car_half_apply_model = new CarHalfApply();
        $this->car_half_apply_credit_model = new CarHalfApplyCredit();
        $this->person_credit = new PersonCredit();
        $this->car_beyond = new CarBeyondOrder();
        //$this->discount_apply_model = new DiscountApply();
        $this->credit_ret_model = new PersonCreditResult();
        $this->dealer_model = new Dealer();
        $this->newcar_dealer_model = new NewCarDealer();
        $this->bank_model = new Bank();
        $this->bank_dealer_model = new DealerBank();
        $this->car_model = new Car();
        $this->cxbrand_model = new CxBrand();
        $this->newcar_cxbrand_model = new NewcarCxBrand();
        $this->cxseries_model = new CxSeries();
        $this->newcar_cxseries_model = new NewcarCxSeries();
        $this->car_detail_model = new CarDetail();
        $this->cxmode_model = new CxMode();
        $this->newcar_cxmode_model = new NewcarCxMode();
        $this->carhalf_detail_model = new CarHalfDetail();
        $this->collect_car_model = new CollectCar();
        $this->ckcar_task_model = new CkCarTask();
        $this->webank_apply_data_model = new WebankApplyData();
        $this->cxmake_model = new CxMake();
        $this->cxmode_finance_model = new CxModeFinance();
        $this->bank_pay_model = new BankPay();
        $this->decision_data_model = new DecisionData();
        $this->loanin_sign_mess_model = new LoaninSignMess();
        $this->erp_master = new ErpMaster();
    }


    /**********面签详情 start*******************/
    //main方法
    public function getVisaDetail($visaInfo){
        $applyId = $visaInfo['apply_id'];
        $masterId = $visaInfo['master_id'];

        $this->applyid = $applyId;
        //订单信息
        $this->getCarHalfApply();
        if(!$this->apply_info){
            return $this->showMsg(self::CODE_FAIL, '未找到订单信息！');
        }
        
        //信审信息
//        $user_info = $this->getUserInfo($this->apply_info['webank_apply_data_id']);
        $user_info = $this->getUserInfoNew($this->apply_info['userid']);

        if(!$user_info){
            return $this->showMsg(self::CODE_FAIL, '未找到信审信息！');
        }

        //获取信审节点applyid
        $this->getCreditApplyId();

        //首付与合同
        $this->getPaymentInfo();

        //车辆与经销商
        $this->getCarDearInfo();

        //获取vin码对应的车型
        $this->getVinCarInfo();

        //刷卡总笔数
        $this->getPayLogCount();

        //补充信息
        $this->getSuppleMentInfo($masterId);

        //补充信息
        $this->getLoaninSignMessInfo();

        //决策引擎信审
//        $this->getDecisionInfo();

        //获取审批记录
//        $this->getRemarkListInfo();


        //数据返回
        $ret = [
            'apply_info' => $this->apply_info,
            'user_info' => $this->user_info,
            'pay_ment_info' => $this->pay_ment_info,
            'pay_ment_info_beyond' => $this->pay_ment_info_beyond,
            'contract_info' => $this->contract_info,
            'beyond_info' => $this->beyond_info,
            'discount_info' => $this->discount_info,
            'dealer_info' => $this->dealer_ret,
            'car_info' => $this->out_carinfo,
            'vin_car_info' => $this->vin_car_info,
            'paylog_count' => $this->paylog_count,
            'supplement_info' => $this->supplement_info,
            'loanin_sign_mess_info' => $this->loanin_sign_mess_info,
//            'decision_info' => $this->decision_info,
//            'remark_info' => $this->remark_info,
            'credit_apply_id' => $this->credit_apply_id,
        ];
        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $ret);
    }

    //首付与合同
    private function getPaymentInfo(){
        //首付款核销明细区
        $this->getFirstPayMentInfo();

        //合同付款区
        $this->getContractInfo();
        //超融明细
        $this->getBeyondInfo();
        //获取贴息信息

        $this->getDiscountInfo();
    }

    //车辆与经销商
    private function getCarDearInfo(){
        //经销商信息
        $this->getDealerInfo();
        //车辆信息
        $this->getCarInfo();
    }

    //获取刷卡总数
    private function getPayLogCount(){
        $carType = isset($this->apply_info['car_type']) ? $this->apply_info['car_type'] : 0;
        $carId = isset($this->apply_info['carid']) ? $this->apply_info['carid'] : 0;

        if($this->paylog_count != null){
            return $this->paylog_count;
        }
        $this->paylog_count = 0;
        if(!$carId){
            return $this->paylog_count;
        }

        $res = $this->bank_pay_model->getFirstSwingCard($carId,$carType);

        $this->paylog_count = isset($res['count']) ? $res['count'] : 0;

        return $this->paylog_count;
    }


    //补充信息
    private function getSuppleMentInfo($masterId){
        $uid = isset($this->apply_info['userid'])?$this->apply_info['userid']:'';

        $this->supplement_info = [];
        if(!$uid){
            return $this->supplement_info;
        }
        if($this->supplement_info){
            return $this->supplement_info;
        }
        $where = ['uid' => $uid];
        $personCredit = $this->getUserInfo('', $where);

        if(!$personCredit){
            return [];
        }
        // 是否需要补录生日/性别/年龄
        $personCredit['lackData'] = 0;
        if (empty($personCredit['birthday']) || empty($personCredit['sex']) || empty($personCredit['age'])) {
            $personCredit['lackData'] = 1;
        }
        if (!$personCredit['birthday']) {
            $personCredit['birthday'] = $this->getBirthday($personCredit['id_card_num']);
        }
        if (!$personCredit['sex']) {
            $personCredit['sex'] = $this->getSex($personCredit['id_card_num']);
        }
        if (!$personCredit['age']) {
            $personCredit['age'] = $this->getAge($personCredit['id_card_num']);
        }
        if (!empty($personCredit['financial_plan'])) {
            $personCredit['financial_plan'] = explode(',', $personCredit['financial_plan']);
        }
        if (!empty($personCredit['car_price'])) {
            $personCredit['car_price'] = round($personCredit['car_price'] / 10000, 2);
        }
        //提交渠道
        $webank_apply_data_id = $this->apply_info['webank_apply_data_id'];
        if($webank_apply_data_id){
            $webank_data = $this->getWebankApplyDataInfo($webank_apply_data_id);

            $submitEntry = config('dict.submit_entry');

            $personCredit['submit_entry'] = isset($submitEntry[$this->apply_info['submit_entry']]) ? $submitEntry[$this->apply_info['submit_entry']]: '';

            $tmpArr = (new RbacMaster())->getOne(['fullname','mobile'], ['masterid' => $masterId]);
            $personCredit['sale_person'] = isset($tmpArr['fullname']) ? $tmpArr['fullname'] : '';
            $personCredit['sale_mobile'] = isset($tmpArr['mobile']) ? $tmpArr['mobile'] : '';
            $personCredit['sale_person_city_name'] =  isset($webank_data['ip_city_name']) ? $webank_data['ip_city_name'] : '';
        }

        $this->supplement_info = $personCredit;
        return $this->supplement_info;
    }

    //获取vin码对应的车型
    private function getVinCarInfo(){
        $applyid = $this->applyid;
        $this->vin_car_info = [];
        if(!$applyid){
            return $this->vin_car_info;
        }

        $car_info = $this->getCarLoanOrderMore($applyid);
        if(!isset($car_info['vin_json'])){
            $car_info['vin_json'] = '';
        }
        $result = json_decode($car_info['vin_json']);
        $car_mode_id = isset($result->car_mode_id)?$result->car_mode_id:'';
        $carid = isset($car_info['carid'])?$car_info['carid']:'';
        if(!$car_mode_id || !$carid){
            return [];
        }
        $vin_car_info = $this->getNewcarCxmodeInfo($car_mode_id);
        $xin_guide_price = $this->getCxModeFinanceInfo($carid);
        $vin_xin_guide_price = $this->getCxModeFinanceInfo($car_mode_id);

        if(empty($car_info) or empty($car_info['vin_json']) or empty($xin_guide_price)){

            $date = ['status' => 1];

        }elseif($result->car_mode_id != 0){

            if($result->car_mode_id == 88888888){
                $date = [
                    'car_mode_name' => $car_info['car_mode_name'],
                    'vin_car_mode_name' => '',
                    'status' => 2,
                    'status_msg' => '无匹配车型',
                ];
                return $date;
            }

            if($car_info['car_mode_name'] == $vin_car_info['modename']){
                $date = [
                    'guide_price' => $xin_guide_price['xin_guide_price'],
                    'car_mode_name' => $car_info['car_mode_name'],
                    'vin_car_mode_name' => $vin_car_info['modename'],
                    'status_msg' =>'无',
                    'status' => 3,
                ];

            }else{

                if($xin_guide_price['xin_guide_price'] < $vin_xin_guide_price['xin_guide_price']){
                    $compare_price = '车辆车型<vin对应车型 相差'.(number_format(($vin_xin_guide_price['xin_guide_price'] - $xin_guide_price['xin_guide_price']),2)).'万';
                }else{
                    $compare_price = '车辆车型>vin对应车型 相差'.(number_format(($xin_guide_price['xin_guide_price'] - $vin_xin_guide_price['xin_guide_price']),2)).'万';
                }

                $date = [
                    'guide_price' => $xin_guide_price['xin_guide_price'],
                    'car_mode_name' => $car_info['car_mode_name'],
                    'vin_guide_price' => $vin_xin_guide_price['xin_guide_price'],
                    'vin_car_mode_name' => $vin_car_info['modename'],
                    'status_msg' => $compare_price,
                    'status' => 4,
                ];
            }

        }else{

            $date = [
                'guide_price' => $xin_guide_price['xin_guide_price'],
                'car_mode_name' => $car_info['car_mode_name'],
                'vin_car_mode_name' => '',
                'status' => 5,
                'status_msg' => '无',
            ];

        }

        $this->vin_car_info = $date;
        return $this->vin_car_info;

    }
    //获取信审补录信息
    public function getLoaninSignMessInfo(){
        $applyid = $this->applyid;
        $loanin_sign_mess_info = $this->loanin_sign_mess_model->getOne(['*'],['applyid' => $applyid]);
        $this->loanin_sign_mess_info = !empty($loanin_sign_mess_info) ? $loanin_sign_mess_info : '';
        return $this->loanin_sign_mess_info;
    }

    //获取信审结果
    public function getDecisionInfo(){

        if(empty($this->credit_apply_id)) {
            return;
        }

        $applyid = $this->credit_apply_id;
        $decision_url = config('common.decision_info_url');
        $params = array(
            'callnode' => 2,
            'applyid' => $applyid,
        );
        $result = HttpRequest::getJson($decision_url, $params);
        if($result['code'] == 0 && !empty($result['data'])) {
            $this->decision_info = array(
                'apply_id' => $applyid,
                'response_data' => json_encode($result['data']),
            );
        }
        return $this->decision_info;
    }

    //获取审批记录
    public function getRemarkListInfo(){
        $applyid = $this->applyid;
        $where = [
            'type' => 3,
            'ids'   => $applyid
        ];

        $remarks = ErpApi::getRemarkInfo($where);
        $userIds = array_column($remarks, 'op_id');
//        $masterWhere = [
//            'in' => [
//                'masterid' => $userIds
//            ]
//        ];
//        $operators = $this->erp_master->getAll('masterid, fullname',$masterWhere);
        $operators = ErpApi::getMasterInfo(['master_ids'=>$userIds]);
        if ($operators['code'] == 1) {
            $operators = $operators['data'];
        } else {
            $operators = [];
        }
        $operators = array_column($operators, 'fullname', 'masterid');
        foreach ($remarks as $k => $v) {
            $remarks[$k]['remark'] = json_decode($v['content'], true);
            $remarks[$k]['fullname'] = isset($operators[$v['op_id']]) ? $operators[$v['op_id']] : '';
        }

        $this->remark_info = $remarks;
        return $this->remark_info;
    }

    //首付款核销明细区
    private function getFirstPayMentInfo(){

        $applyid = $this->applyid;
        $repaymentType = $this->apply_info['repayment_type'];
        $channel_type = $this->apply_info['channel_type'];

        //付一半订单费用明细
        $payment_info = $this->getCarFee($applyid);

        if(!$payment_info){
            return [];
        }

        //超融
        $detailBeyond = $payment_info;
        $carBeyondOrderJsonData=$this->getCarBeyond($applyid);
        $beyondRisk=0;
        if (!empty($carBeyondOrderJsonData['beyond_info'])) {
            $carBeyondOrderData = json_decode($carBeyondOrderJsonData['beyond_info']);
            $beyondRisk = !empty($carBeyondOrderData->beyond_risk)?$carBeyondOrderData->beyond_risk:0;
        }

        //获取超融首付款
        $beyondFirst = 0;
        if (!empty($carBeyondOrderJsonData['beyond_detail'])) {
            $carBeyondOrderDetailData = json_decode($carBeyondOrderJsonData['beyond_detail']);
            $beyondFirst=empty($carBeyondOrderDetailData->beyond_downpayment) ? 0 : $carBeyondOrderDetailData->beyond_downpayment;
        }


        //新车超融订单
        $carBeyondOrderJsonData=$this->getCarBeyond($applyid);

        //超融产品要先读取car_beyond_order.car_order_detail_snap(等额本息)
        if($repaymentType == 2){

            //订单附属表信息
            $exprDetailData = $this->getCarLoanOrderMore($applyid);
            $exprDetailData = json_decode($exprDetailData['expr_detail_data'], true);

            $car_fee_amount = !empty($exprDetailData['value']['car_fee_amount']) ? $exprDetailData['value']['car_fee_amount']*100: 0;
            $overall_fee_amount = !empty($exprDetailData['value']['overall_fee_amount']) ? $exprDetailData['value']['overall_fee_amount'] *100: 0;
            $car_fee_amount = $overall_fee_amount>0 ? $overall_fee_amount : $car_fee_amount;

            //首付车款
            $payment_info['erp_first_pay'] = !empty($exprDetailData['value']['car_down_payment_amount']) ? $exprDetailData['value']['car_down_payment_amount'] * 100 : 0;

            //融资总额
            $payment_info['total_loan'] = !empty($exprDetailData['value']['car_loan_total_amount']) ? $exprDetailData['value']['car_loan_total_amount']* 100: 0;

            //车辆手续费（限牌指标方案）
            $payment_info['car_fee_amount'] = $car_fee_amount;

            //首付款金额
            $payment_info['erp_price_half'] = !empty($exprDetailData['value']['car_pos_amount']) ?$exprDetailData['value']['car_pos_amount'] * 100: 0;

            //合计
            $payment_info['erp_total_price'] = !empty($exprDetailData['value']['car_pos_amount']) ?$exprDetailData['value']['car_pos_amount'] * 100: 0;

            //车辆残值（分）
            $payment_info['scrap_value'] = !empty($exprDetailData['value']['car_salvage_price']) ? $exprDetailData['value']['car_salvage_price']* 100: 0;

            //凯枫服务费
            $payment_info['erp_profit_total'] = !empty($exprDetailData['value']['car_service_amount']) ? $exprDetailData['value']['car_service_amount']* 100 : 0;

            //抵押利息
            $payment_info['erp_interest_mortgage'] = !empty($exprDetailData['value']['car_real_total_interest']) ? $exprDetailData['value']['car_real_total_interest']* 100 : 0;

            //履约保证金
            $payment_info['deposit_fee'] = 0;

        }

        //先息后本
        $carBeyondOrderData = !empty($carBeyondOrderJsonData['car_order_detail_snap'])?json_decode($carBeyondOrderJsonData['car_order_detail_snap'], true):'';
        if($repaymentType != 2 && $carBeyondOrderData){

            foreach($carBeyondOrderData as $k=>$v){
                if (!empty($payment_info[$k])) {
                    $payment_info[$k] = $v;
                }
            }

        }

        $this->pay_ment_info = $payment_info;

        //超融和非超融公共字段处理
        $this->getServiceInfo($detailBeyond);


        //金额转换(分->元)
        $this->pay_ment_info['erp_price_half'] = $detailBeyond['erp_price_half']; //首付款金额
        $this->pay_ment_info = $this->moneyChange($this->pay_ment_info);
        $this->pay_ment_info['beyondRisk'] =$beyondRisk;//风险金


        //（保险）续保押金时间
        $car_loan_order = $this->getCarLoanOrder($applyid);
        $deposit_time = isset($car_loan_order['interest_start'])?date('Y-m-d',$car_loan_order['interest_start']):'';
        $this->pay_ment_info['deposit_time'] = $deposit_time;
        $this->pay_ment_info['gps_name']= $channel_type == 2 ? '风险管理费' : 'GPS费用';


        //含超融
        //首付车款
        $this->pay_ment_info['firstPayBeyond'] = $this->number_format_money($detailBeyond['erp_first_pay']);
        //贷款总额
        $this->pay_ment_info['totalLoanBeyond'] = $this->pay_ment_info['total_loan'];
        //车伯乐服务费(含超融)
        $this->pay_ment_info['cblServiceBeyond'] = $this->pay_ment_info['erp_profit_cbl_beyond'];
        //车伯乐服务费(最终)
        $this->pay_ment_info['cblServiceOverall'] = $this->pay_ment_info['erp_profit_cbl_overall'];
        //优信拍服务费(含超融)
        $this->pay_ment_info['yxpServiceBeyond'] = $this->pay_ment_info['erp_profit_yxp_beyond'];
        //优信拍服务费(最终)
        $this->pay_ment_info['yxpServiceOverall'] = $this->pay_ment_info['erp_profit_yxp_overall'];
        //抵押贷利息(最终)
        $this->pay_ment_info['interestOverall'] = $this->pay_ment_info['erp_interest_mortgage_overall'];
        //抵押贷利息(含超融)
        $this->pay_ment_info['interestBeyond'] = $this->pay_ment_info['erp_interest_mortgage_beyond'];
        // 凯峰服务费(最终)
        $this->pay_ment_info['profit_loan_sum_overall'] = $this->pay_ment_info['erp_profit_total_overall'];
        // 凯峰服务费(含超融)
        $this->pay_ment_info['profit_loan_sum_beyond'] = $this->pay_ment_info['erp_profit_total_beyond'];
        //保证金(含超融)
        $this->pay_ment_info['reserve_amount_beyond'] = $this->pay_ment_info['erp_reserve_amount_beyond'];
        //保证金(最终)
        $this->pay_ment_info['reserve_amount_overall'] = $this->pay_ment_info['erp_reserve_amount_overall'];
        //佣金(含超融)
        $this->pay_ment_info['commission_amount_beyond'] = $this->pay_ment_info['erp_commission_amount_beyond'];
        //佣金(最终)
        $this->pay_ment_info['commission_amount_overall'] = $this->pay_ment_info['erp_commission_amount_overall'];
        //积分(含超融)
        $this->pay_ment_info['point_amount_beyond'] = $this->pay_ment_info['erp_point_amount_beyond'];
        //积分(最终)
        $this->pay_ment_info['point_amount_overall'] = $this->pay_ment_info['erp_point_amount_overall'];
        //合计(最终)
        $this->pay_ment_info['total_price_overall'] = $this->pay_ment_info['erp_total_price_overall'];
        //合计(含超融)
        $this->pay_ment_info['total_price_beyond'] = $this->pay_ment_info['erp_total_price_beyond'];
        //新车超融首付
        $this->pay_ment_info['beyondFirst'] = $beyondFirst;


        return $this->pay_ment_info;
    }


    //超融和非超融公共字段处理
    public function getServiceInfo($detailBeyond){
        $applyid = $this->applyid;

        //费用明细
        $free_detail = $this->getCarFee($applyid);

        //订单附属表信息
        $CarLoanOrderMoreData = $this->getCarLoanOrderMore($applyid);
        $CarLoanOrderMoreData = !empty($CarLoanOrderMoreData['service_clearing'])?json_decode($CarLoanOrderMoreData['service_clearing'],'true'):[];

        /****初始化 start*******/
        //车
        $cblServiceCar = 0;//新车车伯乐服务费(新车服务费)
        $yxpServiceCar = 0;//优信拍服务费
        $kfServiceCar = $free_detail['erp_profit_total'];//凯枫服务费
        $realinterestCar = $free_detail['erp_interest_mortgage'];//抵押贷利息
        $reserveAmountCar = 0;//保证金
        $commissionAmountCar = 0;//佣金
        $pointAmountCar = 0;//积分

        //超融
        $cblServiceBeyond = 0;//新车车伯乐服务费
        $yxpServiceBeyond = 0;//优信拍服务费
        $kfServiceBeyond = $detailBeyond['erp_profit_total'];//凯枫服务费
        $realinterestBeyond = $detailBeyond['erp_interest_mortgage'];//抵押贷利息
        $reserveAmountBeyond = 0;//保证金
        $commissionAmountBeyond = 0;//佣金
        $pointAmountBeyond = 0;//积分

        //最终
        $cblServiceOverall = 0;//新车车伯乐服务费
        $yxpServiceOverall = 0;//优信拍服务费
        $kfServiceOverall = 0;//凯枫服务费
        $realinterestOverall = 0;//抵押贷利息
        $reserveAmountOverall = 0;//保证金
        $commissionAmountOverall = 0;//佣金
        $pointAmountOverall = 0;//积分

        /****初始化 end*******/


        /**///车
        $serviceCar = isset($CarLoanOrderMoreData['amount']['car'])?$CarLoanOrderMoreData['amount']['car']:[];
        //新车服务费
        if(isset($serviceCar['service_cbl_amount'][0])){
            $cblServiceCar = $serviceCar['service_cbl_amount'][0] * 100;
        }
        //优信拍服务费
        if(isset($serviceCar['service_yxp_amount'][0])){
            $yxpServiceCar = $serviceCar['service_yxp_amount'][0];
        }
        //凯枫服务费
        if(isset($serviceCar['service_kf_amount'][0])){
            $kfServiceCar = $serviceCar['service_kf_amount'][0];
        }
        //抵押贷利息
        if(isset($serviceCar['real_interest'][0])){
            $realinterestCar = $serviceCar['real_interest'][0] * 100;
        }
        //保证金
        if(isset($serviceCar['reserve_amount'][0])){
            $reserveAmountCar = $serviceCar['reserve_amount'][0] * 100;
        }
        //佣金
        if(isset($serviceCar['commission_amount'][0])){
            $commissionAmountCar = $serviceCar['commission_amount'][0] * 100;
        }
        //积分
        if(isset($serviceCar['point_amount'][0])){
            $pointAmountCar = $serviceCar['point_amount'][0] * 100;
        }


        /**///首付款核销明细区（超融）
        $serviceBeyond = isset($CarLoanOrderMoreData['amount']['overall'])?$CarLoanOrderMoreData['amount']['overall']:[];
        //新车车伯乐服务费
        if(isset($serviceBeyond['service_cbl_amount'][0])){
            $cblServiceBeyond = $serviceBeyond['service_cbl_amount'][0] * 100;
        }

        //优信拍服务费
        if(isset($serviceBeyond['service_yxp_amount'][0])){
            $yxpServiceBeyond = $serviceBeyond['service_yxp_amount'][0] * 100;
        }

        //凯枫服务费
        if(isset($serviceBeyond['service_kf_amount'][0])){
            $kfServiceBeyond = $serviceBeyond['service_kf_amount'][0] * 100;
        }

        //抵押贷利息
        if(isset($serviceBeyond['real_interest'][0])){
            $realinterestBeyond = $serviceBeyond['real_interest'][0] * 100;
        }

        //保证金
        if(isset($serviceBeyond['reserve_amount'][0])){
            $reserveAmountBeyond = $serviceBeyond['reserve_amount'][0] * 100;
        }

        //佣金
        if(isset($serviceBeyond['commission_amount'][0])){
            $commissionAmountBeyond = $serviceBeyond['commission_amount'][0] * 100;
        }

        //积分
        if(isset($serviceBeyond['point_amount'][0])){
            $pointAmountBeyond = $serviceBeyond['point_amount'][0] * 100;
        }

        /**///首付款核销明细区（最终）
        $serviceOverall = isset($CarLoanOrderMoreData['amount']['overall'])?$CarLoanOrderMoreData['amount']['overall']:[];
//新车车伯乐服务费
        if(isset($serviceOverall['service_cbl_amount'][1])){
            $cblServiceOverall = $serviceOverall['service_cbl_amount'][1] * 100;
        }

        //优信拍服务费
        if(isset($serviceOverall['service_yxp_amount'][1])){
            $yxpServiceOverall = $serviceOverall['service_yxp_amount'][1] * 100;
        }

        //凯枫服务费
        if(isset($serviceOverall['service_kf_amount'][1])){
            $kfServiceOverall = $serviceOverall['service_kf_amount'][1] * 100;
        }

        //抵押贷利息
        if(isset($serviceOverall['real_interest'][1])){
            $realinterestOverall = $serviceOverall['real_interest'][1] * 100;
        }

        //保证金
        if(isset($serviceOverall['reserve_amount'][1])){
            $reserveAmountOverall = $serviceOverall['reserve_amount'][1] * 100;
        }

        //佣金
        if(isset($serviceOverall['commission_amount'][1])){
            $commissionAmountOverall = $serviceOverall['commission_amount'][1] * 100;
        }

        //积分
        if(isset($serviceOverall['point_amount'][1])){
            $pointAmountOverall = $serviceOverall['point_amount'][1] * 100;
        }

        $this->pay_ment_info['erp_total_price_beyond'] = $detailBeyond['erp_total_price'];


        //贴息
        $discountInfo = $this->getDiscountApply($applyid);
        $channelType = $this->apply_info['channel_type'];
        $discountAmount = 0;
        if($discountInfo){
            $discountAmount = $discountInfo['discount_amount'] * 100;
        }

        //首付款核销明细表信息（最终）
        $this->pay_ment_info['erp_total_price'] = $free_detail['erp_total_price'] + $discountAmount;//合计（超融）
        $this->pay_ment_info['erp_total_price_beyond'] = $detailBeyond['erp_total_price'] + $discountAmount;//合计（超融）
        $this->pay_ment_info['erp_total_price_overall'] = $detailBeyond['erp_total_price'];//合计（最终）

        $this->pay_ment_info['erp_profit_cbl_overall'] = $cblServiceOverall;
        $this->pay_ment_info['erp_profit_yxp_overall'] = $yxpServiceOverall;
        $this->pay_ment_info['erp_profit_total_overall'] = $kfServiceOverall;
        $this->pay_ment_info['erp_interest_mortgage_overall'] = $realinterestOverall;
        $this->pay_ment_info['erp_reserve_amount_overall'] = $reserveAmountOverall;
        $this->pay_ment_info['erp_commission_amount_overall'] = $commissionAmountOverall;
        $this->pay_ment_info['erp_point_amount_overall'] = $pointAmountOverall;

        //首付款核销明细表信息
        $this->pay_ment_info['erp_profit_cbl_car'] = $cblServiceCar; //新车服务费
        $this->pay_ment_info['erp_profit_yxp_car'] = $yxpServiceCar; //优信拍服务费
        $this->pay_ment_info['erp_profit_total_car'] = $kfServiceCar; //凯枫服务费
        $this->pay_ment_info['erp_interest_mortgage_car'] = $realinterestCar; //抵押贷利息
        $this->pay_ment_info['erp_reserve_amount_car'] = $reserveAmountCar; //保证金
        $this->pay_ment_info['erp_commission_amount_car'] = $commissionAmountCar; //佣金
        $this->pay_ment_info['erp_point_amount_car'] = $pointAmountCar; //积分

        //首付款核销明细表信息（超融）
        $this->pay_ment_info['erp_profit_total_beyond'] = $kfServiceBeyond;
        $this->pay_ment_info['erp_interest_mortgage_beyond'] = $realinterestBeyond;
        $this->pay_ment_info['erp_profit_cbl_beyond'] = $cblServiceBeyond;
        $this->pay_ment_info['erp_profit_yxp_beyond'] = $yxpServiceBeyond;
        $this->pay_ment_info['erp_reserve_amount_beyond'] = $reserveAmountBeyond;
        $this->pay_ment_info['erp_commission_amount_beyond'] = $commissionAmountBeyond;
        $this->pay_ment_info['erp_point_amount_beyond'] = $pointAmountBeyond;

        return $this->pay_ment_info;
    }


    //合同付款区
    private function getContractInfo(){
        $applyid = $this->applyid;

        //订单费用明细数据
        $order_fee = $this->getCarFee($applyid);
        //订单信息
        $apply_info = $this->getCarHalfApply();

        //信审信息
        $userid = $apply_info['userid'];
        $fund_channel = $apply_info['fund_channel'];
        $credit_ret_info = $this->getCreditRet($userid, $fund_channel);


        //超融贷款额
        $allSuperFinanceCodes = config('common.allSfProductCode');
        $stcode = $apply_info['product_stcode'];
        $isSuperFinance = in_array($stcode, $allSuperFinanceCodes);
        $sfTotalLoan = $isSuperFinance ? $order_fee['sf_total_loan'] : 0;

        // 限牌方案手续费 手续费贷款额
        $isLimiteLicense = isset($credit_ret_info['license_sign']) && ($credit_ret_info['license_sign'] == PersonCreditResult::LICENSE_SIGN_APPROVED);
        $limitLicenseFee = $isLimiteLicense ? $order_fee['car_fee_amount'] : 0;

        $total_loan = isset($order_fee['total_loan'])?$order_fee['total_loan']:0;

        //等额本息
        if ($apply_info['repayment_type'] == config('common.equalInterest')) {

            //付一半订单附属信息
            $carLoanOrderMore = $this->getCarLoanOrderMore($applyid);
            $exprDetail = json_decode($carLoanOrderMore['expr_detail_data'], true);
            $exprDetail = !empty($exprDetail['value']) ? $exprDetail['value'] : '';

            //首付车款(含超融)
            $order_fee['erp_first_pay'] = !empty($exprDetail['car_down_payment_amount']) ? $exprDetail['car_down_payment_amount'] * 100 : 0;

            //融资总额-含超融
            $order_fee['total_loan'] = !empty($exprDetail['car_loan_total_amount']) ? $exprDetail['car_loan_total_amount'] * 100 : 0;

            //车辆手续费（限牌指标方案）
            $car_fee_amount = !empty($exprDetail['car_fee_amount']) ? $exprDetail['car_fee_amount'] * 100 : 0;
            $overall_fee_amount = !empty($exprDetail['overall_fee_amount']) ? $exprDetail['overall_fee_amount'] * 100 : 0;
            $car_fee_amount = $overall_fee_amount > 0 ? $overall_fee_amount : $car_fee_amount;
            $order_fee['car_fee_amount'] = $car_fee_amount;

        } else {

            // 超融产品要先读取 car_beyond_order.car_order_detail_snap 取车辆数据
            $carBeyondOrderJson = $this->getCarBeyond($applyid);
            $orderDetailSnap = !empty($carBeyondOrderJson['car_order_detail_snap'])?json_decode($carBeyondOrderJson['car_order_detail_snap'], true):[];
            if (!empty($orderDetailSnap)) {
                foreach ($orderDetailSnap as $k => $v) {
                    if (!empty($order_fee[$k])) {
                        $order_fee[$k] = $v;
                    }
                }
            }

        }


        //实际应付车款
        if(!isset($order_fee['total_loan'])){
            $order_fee['total_loan'] = 0;
        }
        if(!isset($order_fee['car_fee_amount'])){
            $order_fee['car_fee_amount'] = 0;
        }
        $actualPayCents = $order_fee['total_loan']-$order_fee['car_fee_amount'];
        // 融资总额
        if(!isset($order_fee['sf_total_loan'])){
            $order_fee['sf_total_loan'] = 0;
        }
        $totalRaisedFund = $actualPayCents
            + $order_fee['sf_total_loan']
            + $order_fee['car_fee_amount'];

        if(!isset($order_fee['price_settlement'])){
            $order_fee['price_settlement'] = 0;
        }
        if(!isset($order_fee['erp_first_pay'])){
            $order_fee['erp_first_pay'] = 0;
        }
        $contract_info = [
//            'dealerPrice' => $order_fee['price_settlement'], // 车商车价
            'dealerPrice' => $order_fee['car_price_settlement'], // 车商车价
            'firstPay' => $order_fee['erp_first_pay'], // 首付车款
            'actualPay' => $actualPayCents, // 实际应付车款
            'sfTotalLoan' => $sfTotalLoan, // 超融贷款额
            'limitLicenseFee' => $limitLicenseFee, // 限牌方案手续费
            'totalRaisedFund' => $totalRaisedFund, // 融资总额
        ];

        //car_type = 1新车含超融
        if($apply_info['car_type'] == 1){

            // 首付车款(含超融)
            $contract_info['firstPayBeyond'] = $order_fee['erp_first_pay'];

            // 实际应付车款(即融资总额 且稠州新车算法保留到10位,不能使用结算价-首付车款计算)(含超融)
            $actualPayBeyond = $order_fee['total_loan'] - $order_fee['car_fee_amount'];
            $contract_info['actualPayBeyond'] = $actualPayBeyond;

            //抵押贷到期还款金额
            $contract_info['finalPayBeyond'] = $total_loan;

        }

        $contract_info = $this->_moneyChange($contract_info);
        //到期时间
        $end_years = ceil($apply_info['loan_term']/12);
        $contract_end_time = strtotime('+ '.$end_years.'years', $apply_info['interest_start'] - 3600 * 24);
        $contract_info['contract_expiration_time'] = date('Y-m-d H:i:s', $contract_end_time);

        $this->contract_info = $contract_info;
        return $this->contract_info;
    }

    //超融明细
    private function getBeyondInfo(){
        $applyid = $this->applyid;
        if(!$applyid){
            return [];
        }
        if($this->apply_info['car_type'] != 1){
            return [];
        }
        $carBeyondOrderJsonData=$this->getCarBeyond($applyid);
        if (empty($carBeyondOrderJsonData['beyond_info'])) {
            return [];
        }
        $carBeyondOrderData = json_decode($carBeyondOrderJsonData['beyond_info']);
        $this->beyond_info = $carBeyondOrderData->beyond_info;

        return $this->beyond_info;
    }

    //贴息信息
    private function getDiscountInfo(){
        $applyid = $this->applyid;
        if(!$applyid){
            return [];
        }
        if($this->apply_info['car_type'] != 1){
            return [];
        }
        $discountInfo = $this->getDiscountApply($applyid);

        if (!empty($discountInfo)) {
            $discountInfo['discount_type'] = config('common.discount_type')[$discountInfo['discount_type']];

        } else {
            $discountInfo['discount_type'] = '无';
            $discountInfo['discount_amount'] = 0;
        }

        $this->discount_info = $discountInfo;
        return $this->discount_info;
    }

    //经销商信息
    private function getDealerInfo(){
        $apply_info = $this->apply_info;
        $channel_type = isset($apply_info['channel_type'])?$apply_info['channel_type']:'';
        $dealerid = isset($apply_info['dealerid'])?$apply_info['dealerid']:'';

        if(!$channel_type || !$dealerid){
            return [];
        }

        $bank = '';
        $dealer = '';
        if($channel_type == 1){//二手车
            $dealer = $this->getDealer($dealerid);
            $bank = $this->getBank($dealerid);
        } else {//新车
            $dealer = $this->getNewDealer($dealerid);
            $bank = $this->getBankDealer($dealerid);
            $bank['title'] = isset($bank['account_name']) ? $bank['account_name'] : '';
        }

        $this->dealer_ret = [
            'dealername' => isset($dealer['dealername']) ? $dealer['dealername'] : '',
            'address' => isset($dealer['address']) ? $dealer['address'] : '',
            'bank_name' => isset($bank['bank_name']) ? $bank['bank_name'] : '',
            'bank_no' => isset($bank['bank_no']) ? $bank['bank_no'] : '',
            'title' => isset($bank['title']) ? $bank['title'] : '',
            'bank_code' => isset($bank['bank_code']) ? $bank['bank_code'] : '',
        ];
        return $this->dealer_ret;

    }
    //车辆信息
    private function getCarInfo(){
        //car
        $channel_type = $this->apply_info['channel_type'];
        $carid = $this->apply_info['carid'];
        $applyid = $this->applyid;
        $this->out_carinfo = [];
        $carInfo = [];

        if(!$channel_type || !$carid){
            return $this->out_carinfo ;
        }

        //二手车
        if($channel_type == 1){

            $carInfo = $this->getCar($carid);
            $brandid = isset($carInfo['brandid'])?$carInfo['brandid']:'';
            $seriesid = isset($carInfo['seriesid'])?$carInfo['seriesid']:'';
            $modeid = isset($carInfo['modeid'])?$carInfo['modeid']:'';
            if(!$brandid || !$seriesid || !$modeid){
                return $this->out_carinfo ;
            }
            $brand   = $this->getCxbrandInfo($brandid);
            $series = $this->getCxSeriesInfo($seriesid);
            $carDetail = $this->getCarDetailInfo($carid);
            $model = $this->getCxModeInfo($modeid);
            $car_loan_order_info = $this->getCarLoanOrder($applyid);
            $car_loan_order_more_info = $this->getCarLoanOrderMore($applyid);

            //是否有复检
            $recheckResult = $this->getRecheckResultOfByCarid($carid);
            $carInfo = [
                'carid' => $carInfo['carid'],
                'color' => !empty($carDetail['color_remark']) ? $carDetail['color_remark'] : '',
                'vin'   => $carDetail['vin'],
                'brandname' => $brand['brandname'],
                'seriesname' => $series['seriesname'],
                'modename' => $model['modename'],
                'registdate' => $carInfo['regist_date'], //首次上牌日期
                'enginenum'  => $carDetail['engine_num'], //发动机号
                'vin_pay'    => $car_loan_order_info['vin'], //刷卡时vin
                'series_mode'=> $car_loan_order_more_info['car_series_name'].' '.$car_loan_order_more_info['car_mode_name'], //刷卡时车系车型
                'car_regist_date' => date('Y-m-d', strtotime($car_loan_order_more_info['car_regist_date'])), //刷卡时间
                'recheckResult'  => $recheckResult,
                'contract_code' => $car_loan_order_more_info['contract_code'],
            ];

        }

        $webank_apply_data_id = $this->apply_info['webank_apply_data_id'];
        if(!$webank_apply_data_id){
            return $this->out_carinfo ;
        }

        //车伯乐
        if ($channel_type == 2){

            $webank_data = $this->getWebankApplyDataInfo($webank_apply_data_id);
            $from_mode_id = isset($webank_data['from_mode_id'])?$webank_data['from_mode_id']:[];
            if(!$from_mode_id){
                return $this->out_carinfo ;
            }
            $mode_info = $this->getNewcarCxmodeInfo($from_mode_id);
            $brandid = isset($mode_info['brandid'])?$mode_info['brandid']:'';
            $seriesid = isset($mode_info['seriesid'])?$mode_info['seriesid']:'';
            $makeid = isset($mode_info['makeid'])?$mode_info['makeid']:'';

            $brand_info = '';
            $series_info = '';
            $make_info = '';
            if($brandid){
                $brand_info = $this->getNewcarCxbrandInfo($brandid);
            }
            if($seriesid){
                $series_info = $this->getNewcarCxSeriesInfo($seriesid);
            }
            if($makeid){
                $make_info = $this->getCxMake($makeid);
            }

            $brandName = !empty($brand_info) ? $brand_info['brandname'] : '';
            $makeName = !empty($make_info) ? $make_info['makename'] : '';
            $seriesName = !empty($series_info) ? $series_info['seriesname'] : '';
            $modeName = !empty($mode_info) ? $mode_info['modename'] : '';
            $credit_info = empty($webank_data) ? '无' : $brandName . ' ' . $makeName . ' ' . $seriesName . ' ' . $modeName;


            $cxModeFinanceData = $this->getCxModeFinanceInfo($carid);
            $car_loan_order_info = $this->getCarLoanOrder($applyid);
            $car_loan_order_more_info = $this->getCarLoanOrderMore($applyid);
            $vinNumber ='';
            if(!empty($car_loan_order_more_info['vin_json'])){
                $vinJson = json_decode($car_loan_order_more_info['vin_json'],true);
                $vinNumber = !empty($vinJson['vin']) ? $vinJson['vin'] : '';
            }
            $vin = !empty($car_loan_order_info['vin']) ? strtoupper($car_loan_order_info['vin']) : strtoupper($vinNumber);
            $carInfo = [
                'carid' => $car_loan_order_info['carid'],
                'brandname' => $car_loan_order_more_info['car_brand_name'],
                'seriesname' => $car_loan_order_more_info['car_series_name'],
                'carname' => $car_loan_order_more_info['car_name'],
                'modename' => $car_loan_order_more_info['car_mode_name'],
                'color' => $car_loan_order_more_info['car_color'],
                'enginenum' => strtoupper($car_loan_order_more_info['engine_num']),
                'xin_guide_price' => !empty($cxModeFinanceData['xin_guide_price']) ? $cxModeFinanceData['xin_guide_price'] * 10000 : 0,//优信指导价
                'credit_info' => $credit_info,
                'vin' => $vin,
                'series_mode' => $car_loan_order_more_info['car_series_name'].' '.$car_loan_order_more_info['car_mode_name'], //刷卡时车系车型
            ];
            //dd($carInfo);
        }
        $this->out_carinfo = $carInfo;
        return $this->out_carinfo;
    }

    /*
     * @desc 获取二手车车辆复检状态以及免责书地址
     *
     * @param $carid int 车辆Id
     *
     * @return $recheckResult []
     */
    private function getRecheckResultOfByCarid($carid){
        $masterCarId = $this->getCarHalfDetailInfo($carid);
        if($masterCarId == 0){
            return [];
        }
        $masterCarId = $masterCarId['master_carid'];
        $type = $this->getCar($masterCarId);
        if($type['type'] == 1){
            $finalCarId = $this->getCollectCarInfo($masterCarId);
            $masterCarId = $finalCarId['c_carid'];
        }else{
            $masterCarId = $carid;
        }
        $ckCarTask = $this->getCkCarTaskInfo($masterCarId);
        if($ckCarTask){
            return $ckCarTask['task_status'];
        }
    }


    //二手车经销商
    private function getDealer($dealerid){
        if(!$dealerid){
            return [];
        }
        if(isset($this->dealer_info[$dealerid])){
            return $this->dealer_info[$dealerid];
        }

        $this->dealer_info[$dealerid] = $this->dealer_model->getOne(['dealername','address'],['dealerid' => $dealerid]);
        return $this->dealer_info[$dealerid];
    }

    //新车经销商
    private function getNewDealer($dealerid){
        if(!$dealerid){
            return [];
        }
        if(isset($this->newcar_dealer_info[$dealerid])){
            return $this->newcar_dealer_info[$dealerid];
        }

        $this->newcar_dealer_info[$dealerid] = $this->newcar_dealer_model->getOne(['dealername','address'],['dealerid' => $dealerid]);
        return $this->newcar_dealer_info[$dealerid];
    }

    //银行信息
    private function getBank($dealerid){
        if(!$dealerid){
            return [];
        }
        if(isset($this->bank_info[$dealerid])){
            return $this->bank_info[$dealerid];
        }
        $this->bank_info[$dealerid] = $this->bank_model->getOne(['bank_name','bank_no','title','bank_code'],['dealerid' => $dealerid]);
        return $this->bank_info[$dealerid];
    }

    //经销商银行信息
    private function getBankDealer($dealerid){
        if(!$dealerid){
            return [];
        }
        if(isset($this->bank_dealer_info[$dealerid])){
            return $this->bank_dealer_info[$dealerid];
        }
        $this->bank_dealer_info[$dealerid] = $this->bank_dealer_model->getOne(['bank_name','bank_no','account_name','bank_code'],['dealerid' => $dealerid]);
        return $this->bank_dealer_info[$dealerid];
    }



    //订单信息
    public function getCarHalfApply(){

        if($this->apply_info){
            return $this->apply_info;
        }
        $apply_fields = [
            'applyid','car_type','product_stcode','loan_term','channel_type',
            'cityid','webank_apply_data_id','repayment_type','userid','fund_channel',
            'finance_time','interest_start','contract_id','finance_pay_id','dealerid',
            'carid','submit_entry', 'rent_type'
        ];


        $this->apply_info = $this->car_half_apply_model->getOne($apply_fields,['applyid'=>$this->applyid]);
        return $this->apply_info;
    }

    //获取用户新
    private function getUserInfo($webank_apply_data_id, $where = false){
        if(!$webank_apply_data_id && $where == false){
            return [];
        }
        if($this->user_info && $where == false){
            return $this->user_info;
        }
        if($where == false){
            $where = ['webank_apply_data_id'=>$webank_apply_data_id];
        }
        $this->user_info = $this->person_credit->getOne(['*'],$where);
        return $this->user_info;
    }
    private function getUserInfoNew($userid, $where = false){
        if(!$userid && $where == false){
            return [];
        }
        if($this->user_info && $where == false){
            return $this->user_info;
        }
        if($where == false){
            $where = ['uid'=>$userid];
        }
        $this->user_info = $this->person_credit->getOne(['*'],$where,[ "creditid" => "desc"]);
        return $this->user_info;
    }

    private function getCreditApplyId() {

        $this->credit_apply_id = '';
        $uid = $this->user_info['uid'];
        $fields = array('applyid');
        $conds = array(
            'userid' => $uid,
            'webank_apply_data_id' =>  $this->apply_info['webank_apply_data_id'],
            'carid' => 0,
            'rent_type' => 2//只查直租
        );
        $orderBy = array(
            "applyid" => "desc",
        );
        $result = $this->car_half_apply_credit_model->getOne($fields,$conds,$orderBy);
        if(!empty($result)) {
            $this->credit_apply_id = $result['applyid'];
        }
    }

    //信审结果表
    private function getCreditRet($userid, $fund_channel){
        if(!$userid || !$fund_channel){
            return [];
        }
        $key = $userid . '_' . $fund_channel;
        if(isset($this->credit_ret_info[$key])){
            return $this->credit_ret_info[$key];
        }
        $fields = ['license_sign'];
        $where = ['uid' => $userid, 'bank_id' => $fund_channel];
        $this->credit_ret_info[$key] = $this->credit_ret_model->getOne($fields, $where);

        return $this->credit_ret_info[$key];
    }

    //付一半订单费用明细
    private function getCarFee($applyid){
        if($this->free_detail_info){
            return $this->free_detail_info;
        }
        if(!$applyid){
            return [];
        }
        $order_fee = ErpApi::getCarFee($applyid);
        $order_fee = !empty($order_fee[$applyid])? $order_fee[$applyid]:[];
        $this->free_detail_info = $order_fee;
        return $this->free_detail_info;
    }

    //外部信息
    public function getOutsideInfo($condition){
        if($this->outside_info){
            return $this->outside_info;
        }
        if(!$condition){
            return [];
        }

        $this->outside_info = ErpApi::getOutsideInfo($condition);
        return $this->outside_info;
    }

    //新车超融订单信息
    private function getCarBeyond($applyid){
        if($this->car_beyond_info){
            return $this->car_beyond_info;
        }
        if(!$applyid){
            return [];
        }
        $this->car_beyond_info = $this->car_beyond->getOne(['beyond_info','car_order_detail_snap','beyond_detail'],['apply_id'=>$applyid,'status >='=>1], ['apply_id' => 'DESC']);

        return $this->car_beyond_info;
    }

    //订单附属信息
    private function getCarLoanOrderMore($applyid){
        if($this->car_loan_order_more_info){
            return $this->car_loan_order_more_info;
        }
        if(!$applyid){
            return [];
        }

        $feilds = ['expr_detail_data','service_clearing','car_regist_date','car_series_name','car_mode_name','car_brand_name','vin_json', 'car_name', 'car_color','engine_num','carid','guide_price','contract_code'];
        $this->car_loan_order_more_info = $this->loan_more_model->getOne($feilds, ['applyid' => $applyid]);

        return $this->car_loan_order_more_info;
    }

    //car_loan_order付一半订单
    private function getCarLoanOrder($applyid){
        if(!$applyid){
            return [];
        }

        if($this->car_loan_order_info){
            return $this->car_loan_order_info;
        }
        $this->car_loan_order_info = $this->car_loan_model->getOne(['interest_start','vin','carid'],['applyid'=>$applyid]);
        return $this->car_loan_order_info;
    }

    //贴息信息
    private function getDiscountApply($applyid){

        return [];

        if(!$applyid){
            return [];
        }
        if($this->discount_apply_info){
            return $this->discount_apply_info;
        }
        $fields = ['discount_type', 'discount_amount','youxin_amount','factory_amount'];
        $this->discount_apply_info = $this->discount_apply_model->getOne(
            $fields,
            ['applyid' => $applyid, 'status' => 0]
        );

        return $this->discount_apply_info;
    }

    //car
    private function getCar($carid){
        if(!$carid){
            return [];
        }
        if(isset($this->car_info[$carid])){
            return $this->car_info[$carid];
        }
        $fields = ['brandid','seriesid','modeid','carid','regist_date','type'];
        $this->car_info[$carid] = $this->car_model->getOne($fields,['carid'=>$carid]);
        return $this->car_info[$carid];
    }

    //CxBrand
    private function getCxbrandInfo($brandid){
        if(!$brandid){
            return [];
        }
        if(isset($this->cxbrand_info[$brandid])){
            return $this->cxbrand_info[$brandid];
        }
        $fields = ['brandname'];
        $this->cxbrand_info[$brandid] = $this->cxbrand_model->getOne($fields,['brandid'=>$brandid]);
        return $this->cxbrand_info[$brandid];
    }

    //newcar CxBrand
    private function getNewcarCxbrandInfo($brandid){
        if(!$brandid){
            return [];
        }
        if(isset($this->newcar_cxbrand_info[$brandid])){
            return $this->newcar_cxbrand_info[$brandid];
        }
        $fields = ['brandname'];
        $this->newcar_cxbrand_info[$brandid] = $this->newcar_cxbrand_model->getOne($fields,['brandid'=>$brandid]);
        return $this->newcar_cxbrand_info[$brandid];
    }

    //CxSeries
    private function getCxSeriesInfo($seriesid){
        if(!$seriesid){
            return [];
        }
        if(isset($this->cxseries_info[$seriesid])){
            return $this->cxseries_info[$seriesid];
        }
        $fields = ['seriesname'];
        $this->cxseries_info[$seriesid] = $this->cxseries_model->getOne($fields,['seriesid'=>$seriesid]);
        return $this->cxseries_info[$seriesid];
    }

    //newcar CxSeries
    private function getNewcarCxSeriesInfo($seriesid){
        if(!$seriesid){
            return [];
        }
        if(isset($this->newcar_cxseries_info[$seriesid])){
            return $this->newcar_cxseries_info[$seriesid];
        }
        $fields = ['seriesname'];
        $this->newcar_cxseries_info[$seriesid] = $this->cxseries_model->getOne($fields,['seriesid'=>$seriesid]);
        return $this->newcar_cxseries_info[$seriesid];
    }

    //CarDetail
    private function getCarDetailInfo($carid){
        if(!$carid){
            return [];
        }
        if(isset($this->car_detail_info[$carid])){
            return $this->car_detail_info[$carid];
        }
        $fields = ['color_remark','vin','engine_num'];
        $this->car_detail_info[$carid] = $this->car_detail_model->getOne($fields,['carid'=>$carid]);
        return $this->car_detail_info[$carid];
    }

    //CxMode
    private function getCxModeInfo($modeid){
        if(!$modeid){
            return [];
        }
        if(isset($this->cxmode_info[$modeid])){
            return $this->cxmode_info[$modeid];
        }
        $fields = ['modename'];
        $this->cxmode_info[$modeid] = $this->cxmode_model->getOne($fields,['modeid'=>$modeid]);
        return $this->cxmode_info[$modeid];
    }

    //newcar CxMode
    private function getNewcarCxmodeInfo($modeid){
        if(!$modeid){
            return [];
        }
        if(isset($this->newcar_cxmode_info[$modeid])){
            return $this->newcar_cxmode_info[$modeid];
        }
        $fields = ['seriesid', 'brandid', 'makeid','modename','guideprice'];
        $this->newcar_cxmode_info[$modeid] = $this->newcar_cxmode_model->getOne($fields,['modeid'=>$modeid]);
        return $this->newcar_cxmode_info[$modeid];
    }

    //CarHalfDetail
    private function getCarHalfDetailInfo($carid){
        if(!$carid){
            return [];
        }
        if(isset($this->carhalf_detail_info[$carid])){
            return $this->carhalf_detail_info[$carid];
        }
        $fields = ['master_carid'];
        $this->carhalf_detail_info[$carid] = $this->carhalf_detail_model->getOne($fields,['carid'=>$carid]);
        return $this->carhalf_detail_info[$carid];
    }

    //collect_car 采集车源表
    private function getCollectCarInfo($carid){
        if(!$carid){
            return [];
        }
        if(isset($this->collect_car_info[$carid])){
            return $this->collect_car_info[$carid];
        }
        $fields = ['c_carid'];
        $this->collect_car_info[$carid] = $this->collect_car_model->getOne($fields,['carid'=>$carid]);
        return $this->collect_car_info[$carid];
    }

    //ck_car_task 检测报告表
    private function getCkCarTaskInfo($masterCarId){
        if(!$masterCarId){
            return [];
        }
        if(isset($this->ckcar_task_info[$masterCarId])){
            return $this->ckcar_task_info[$masterCarId];
        }
        $fields = ['task_status'];
        $this->ckcar_task_info[$masterCarId] = $this->ckcar_task_model->getOne($fields,['source_car_id'=>$masterCarId,'type'=>2]);
        return $this->ckcar_task_info[$masterCarId];
    }

    //webank_apply_data 信审四要素提交表
    private function getWebankApplyDataInfo($webank_apply_data_id){
        if(!$webank_apply_data_id){
            return [];
        }
        if(isset($this->webank_apply_data_info[$webank_apply_data_id])){
            return $this->webank_apply_data_info[$webank_apply_data_id];
        }
        $fields = ['commit_longitude', 'commit_latitude', 'from_mode_id', 'submit_entry', 'city_id', 'username', 'operator_id','ip_city_name','username','from_platform'];
        $this->webank_apply_data_info[$webank_apply_data_id] = $this->webank_apply_data_model->getOne($fields,['id'=>$webank_apply_data_id]);
        return $this->webank_apply_data_info[$webank_apply_data_id];
    }

    //CxMake 车辆厂商表
    private function getCxMake($makeid){
        if(!$makeid){
            return [];
        }
        if(isset($this->cxmake_info[$makeid])){
            return $this->cxmake_info[$makeid];
        }
        $fields = ['makename'];
        $this->cxmake_info[$makeid] = $this->cxmake_model->getOne($fields,['makeid'=>$makeid]);
        return $this->cxmake_info[$makeid];
    }

    //cx_mode_finance 车型金融属性表
    private function getCxModeFinanceInfo($modeid){
        if(!$modeid){
            return [];
        }
        if(isset($this->cxmode_finance_info[$modeid])){
            return $this->cxmode_finance_info[$modeid];
        }
        $fields = ['xin_guide_price'];
        $this->cxmode_finance_info[$modeid] = $this->cxmode_finance_model->getOne($fields,['modeid'=>$modeid]);
        return $this->cxmode_finance_info[$modeid];
    }


    //金额转换(分->元)
    private function moneyChange($info){
        $result = [];
        if (empty($info)) {
            return $result;
        }

        $result = $this->_moneyChange($info);

        $erp_interest_sum = $info['erp_interest_mortgage'] + $info['erp_interest_credit'] + $info['erp_sf_interest'];
        if (!empty($erp_interest_sum)) {
            $erp_interest_sum = bcdiv($erp_interest_sum, 100 ,2);
        }
        $result['erp_interest_sum'] = $this->number_format_money($erp_interest_sum);

        $result['interest_loan_sum'] = $result['erp_interest_sum'];
        $result['deposit_fee'] = $this->number_format_money(bcdiv($info['deposit_fee'], 100));

        $car_loan_order_info = $this->getCarLoanOrder($info['applyid']);
        $result['deposit_time'] = $info['deposit_fee'] ? $car_loan_order_info['interest_start'] : '-';
        // 利息和融资租赁费(含超融)
        $result['interest_lease_fee_total'] = $this->number_format_money(
            bcdiv($info['sf_interest_lease_fee'] + $info['erp_interest_total'], 100)
        );
        // 利息和融资租赁费(含超融)
        if(isset($info['interest_lease_fee_total_new_car'])){
            $result['interest_lease_fee_total_new_car'] = $this->number_format_money(
                bcdiv($info['interest_lease_fee_total_new_car'],100)
            );
        }

        return $result;
    }

    private function _moneyChange($info){
        $exclude = [
            'id',
            'applyid',
            'carid',
            'master_carid',
            'fee_limit',
            'userid',
        ];
        $result = [];
        foreach ($info as $key => $val) {
            if(in_array($key, $exclude)){
                $result[$key] = $val;
                continue;
            }
            switch ($key) {
                case 'price_settlement':
                    $result[$key] = $this->number_format_money($val);
                    // 零售价，以万为价格单位
                    $result['lsj'] = $this->number_format_money($val, 1000000);
                    break;
                default:
                    $result[$key] = $this->number_format_money($val);
            }
        }
        return $result;
    }




    /**********面签详情 end*******************/


    /**
     * 获取生日
     * @param $value
     * @return string
     */
    public function getBirthday($value)
    {
        if (!$value) {
            return "";
        }

        $year = "1900";
        $month = "1";
        $day = "1";
        if (strlen($value) == 15) {
            $year = "19" + substr($value, 6, 2);
            $month = substr($value, 8, 2);
            $day = substr($value, 10, 2);
        } else if (strlen($value) == 18) {
            $year = substr($value, 6, 4);
            $month = substr($value, 10, 2);
            $day = substr($value, 12, 2);
        } else {
            return "";
        }
        return $year . "-" . $month . "-" . $day;
    }

    /**
     * 获取性别
     * value 身份证号码
     */
    function getSex($value)
    {
        if (!$value) {
            return "未知";
        } else if (strlen($value) == 15) {
            $mySex = substr($value, -1, 1); //性别
        } else if (strlen($value) == 18) {
            $mySex = substr($value, -2, 1); //性别
        } else {
            return 0;
        }

        //性别验证
        if (($mySex % 2) == 0) {
            return 2;
        } else {
            return 1;
        }
    }

    /**
     * 获取年龄
     * @param $value
     * @return int
     */
    public function getAge($value)
    {
        $birthday = $this->getBirthday($value);
        $birthday = explode('-', $birthday);
        $now = date('Y');
        $age = intval($now) - intval($birthday[0]);

        return $age;
    }

    /**
     * 格式化金额
     * @param $money
     * @param $p
     * @return string
     */
    public function number_format_money($money, $p = 100){
        if (empty($money)) {
            return number_format(0, 2);
        }
        return number_format($money/$p, 2);
    }

    /**
     * 返回结果
     * @param int $code
     * @param string $message
     * @param array $data
     * @return mixed
     */
    public function showMsg($code = self::CODE_FAIL, $message = self::MSG_PARAMS, $data = [])
    {
        $resData = [
            'code' => $code,
            'msg' => $message,
            'data' => $data
        ];

        return $resData;
    }


    public static function get_fishing_back($credit_apply_id)
    {
        $arr = [];
        if(empty($credit_apply_id)){
            return [];
        }
        if (!is_array($credit_apply_id)) {
            $credit_apply_id = [$credit_apply_id];
        }
        $res = CreditApi::get_back_detail(implode(',',$credit_apply_id));

        if(!empty($res['code']) &&$res['code'] == 1 && !empty($res['data'])){
            foreach ($res['data'] as $key => $val) {
                $str1 = $str2 = [];
                foreach ($val as $kk => $vv) {
                    if(!empty($vv['bank_id']) && !empty($vv['direct_allow_back']) && $vv['direct_allow_back'] == 1){
                        switch ($vv['bank_id']) {
                            case 1 :
                                $str1[] = '微众捞回';
                                break;
                            case 10 :
                                $str1[] = '新网捞回';
                                break;
                            case 18 :
                                $str1[] = '民生捞回';
                                break;
                        }
                    }
                    if(!empty($vv['bank_id']) && !empty($vv['direct_allow_back']) && $vv['direct_allow_back'] == 2){
                        switch ($vv['bank_id']) {
                            case 1 :
                                $str2[] = '微众捞回';
                                break;
                            case 10 :
                                $str2[] = '新网捞回';
                                break;
                            case 18 :
                                $str2[] = '民生捞回';
                                break;
                        }
                    }
                }
                if($str1){
                    $arr[$key] .= '一捞:'.implode(',',$str1);
                    if($str2){
                        $arr[$key] .= ' ; 二捞:'.implode(',',$str2);
                    }
                }elseif(!$str1 && $str2){
                    $arr[$key] .= ' 二捞:'.implode(',',$str2);
                }

            }
        }
        return $arr;
    }

    //获取租贷期数
    public static function getLoanTerm($applyids){
        if (!is_array($applyids)) {
            $applyids = array($applyids);
        }
        $car_half_apply_model = new CarHalfApply();
        $terms = $car_half_apply_model->getAll(['applyid','loan_term'],['in'=> ['applyid' => $applyids ] ]);
        if ($terms) {
            $terms = array_column($terms,'loan_term','applyid');
            return $terms;
        }
        return [];
    }

    //销售姓名城市
    public static function getSaleNameCity($applyids)
    {
        if (!is_array($applyids)) {
            $applyids = array($applyids);
        }
        $car_half_apply_model = new CarHalfApply();
        $res = $car_half_apply_model->getAll(['applyid','webank_apply_data_id'],['in'=> ['applyid' => $applyids ] ]);
        $result = [];
        if ($res) {
            $arrs = array_column($res,null,'webank_apply_data_id');
            $webank_apply_data_ids = array_column($res,'webank_apply_data_id');
            $webank_apply_data_model = new WebankApplyData();
            $webanks =$webank_apply_data_model->getAll(['username','ip_city_name','id'],['in' => ['id' => $webank_apply_data_ids]]);
            if ($webanks) {
                $webanks = array_column($webanks,null,'id');
                foreach ($arrs as $k => $v ) {
                    $result[$v['applyid']]['user_name'] = $webanks[$k]['username'];
                    $result[$v['applyid']]['ip_city_name'] = $webanks[$k]['ip_city_name'];
                }
            }
        }
        return $result;
    }

}