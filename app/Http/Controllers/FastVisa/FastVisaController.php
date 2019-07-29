<?php
/**
 * Created by PhpStorm.
 * User: tanrenzong
 * Date: 18/2/6
 * Time: 下午10:36
 */
namespace App\Http\Controllers\FastVisa;

use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Library\Helper;
use App\Library\RedisCommon;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaExtend;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\FastVisaWzFaceResult;
use App\Models\VideoVisa\ImAccount;
use App\Models\VideoVisa\ImRbacMaster;
use App\Models\VideoVisa\NetEase\FastVideoData;
use App\Models\VideoVisa\RbacMasterComment;
use App\Models\VideoVisa\Role;
use App\Models\VideoVisa\SeatManage;
use App\Models\Xin\BankPay;
use App\Models\Xin\City;
use App\Models\Xin\RbacMaster;
use App\Repositories\CityRepository;
use App\Repositories\CommonRepository;
use App\Repositories\CreditRepository;
use App\Repositories\Face\WzFace;
use App\Repositories\FaceAuthRepository;
use App\Repositories\FaceRecognitionRepository;
use App\Repositories\UserRepository;
use App\Repositories\Visa\VisaRepository;
use App\User;
use App\XinApi\ErpApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use App\Models\Xin\CarHalfApply;
use App\Models\XinFinance\CarHalfService;
use App\Fast\FastException;
use App\XinApi\CreditApi;
use League\Flysystem\Adapter\Local;
use Mockery\Exception;

class FastVisaController extends BaseController{

    //新网资方
    const FUND_CHANNEL_XINWANG = 10;

    private $interview_status = [];
    private $bank_type = [];
    private $bankPayModel;
    public $redis = null;
    public $wz_face = null;

    //private $business_types = [];
    function __construct() {
        parent::__construct();
        $this->interview_status = config('dict.interview_status');
        $this->bank_type = config('dict.bank_type');
        $this->bankPayModel = new BankPay();
        $this->redis = new RedisCommon();
        $this->wz_face = new WzFace();
    }

    /**
     * 待审核列表页
     * 坐席:
        待审核列表:展示所有可领取,处理中,视频中,挂起排队订单,
        挂起列表:展示所有自己的挂起,挂起排队订单
        管理员:
        待审核列表:展示所有可领取,处理中,视频中,挂起排队订单,
        挂起列表:展示所有挂起,挂起排队订单.
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function actionWaitList(Request $request)
    {
        $requestParams = $request->all();
        $table = !empty($requestParams['table'])? $requestParams['table']:'';
        $seat_id = session('uinfo.seat_id');
        $visaRepository = new VisaRepository();
        $seat_manager_obj = new SeatManage();
        $seat_list = $seat_manager_obj->get_effective_seat();
        $free_status = $seat_manager_obj->countBy(['work_status' => SeatManage::SEAT_WORK_STATUS_FREE,'id' => $seat_id,'flag' => Role::FLAG_FOR_RISK]);
        $visa_obj = new FastVisa();
        $hang_count = $visa_obj->countBy(['seat_id'=>$seat_id,'in' => ['status'=>[FastVisa::VISA_STATUS_HANG, FastVisa::VISA_STATUS_HANG_QUEUEING]]]);
        if($table && $request->isMethod('post')){
           switch ($table){
               case 'wait':
                   $waitListCondition = $this->formatParamList($requestParams);
                   $list = $visaRepository->getNeedVisaList($waitListCondition);
                   return $this->_page_format($list['data'],$list['count']);
                   break;
               case 'hang':
                   $hangListCondition = $this->formatParamList($requestParams);
                   $list = $visaRepository->getHangList($hangListCondition);
                   return $this->_page_format($list['data'],$list['count']);
                   break;
               default:
           }
        }else{
            $sales_type = CarHalfService::purchase_map(); //获取渠道类型
            $data = [
                'erp_credit_status' => FastVisa::$visaErpStatusChineseMap,
                'sales_type' => $sales_type,
                'interview_status' => FastVisa::$visaStatusChineseMap,
                'hang_count' => $hang_count,
                'seat_list' => $seat_list
            ];
            if($free_status){
                $data['seat_id'] = session('uinfo.flag') == 3? $seat_id:0;//风控
            }
            return view('fast_visa.wait_list',$data);
        }
    }
    /**
     * 复议列表
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function actionReconsList(Request $request)
    {
        $requestParams = $request->all();
        $table = !empty($requestParams['table'])? $requestParams['table']:'';
        $seat_id = session('uinfo.seat_id');
        $visaRepository = new VisaRepository();
        $seat_manager_obj = new SeatManage();
        $seat_list = $seat_manager_obj->get_effective_seat();
        $free_status = $seat_manager_obj->countBy(['work_status' => SeatManage::SEAT_WORK_STATUS_FREE,'id' => $seat_id,'flag' => Role::FLAG_FOR_RISK]);
        $visa_obj = new FastVisa();
        if($table && $request->isMethod('post')){
            switch ($table){
                case 'recons':
                    $reconsListCondition = $this->formatParamList($requestParams);
                    $list = $visaRepository->getReconsVisaList($reconsListCondition);
                    return $this->_page_format($list['data'],$list['count']);
                    break;
                default:
            }
        }else{
            $sales_type = CarHalfService::purchase_map(); //获取渠道类型
            $data = [
                'erp_credit_status' => FastVisa::$visaErpStatusChineseMap,
                'sales_type' => $sales_type,
                'seat_list' => $seat_list
            ];
            if($free_status){
                $data['seat_id'] =$seat_id;
            }
            return view('fast_visa.recons_list',$data);
        }
    }
    /**
     * 信息查询列表
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function actionAllList(Request $request)
    {
        $seat_manager_obj = new SeatManage();
        $seat_effect_list = $seat_manager_obj->get_effective_seat();
        if($request->isMethod('post')){
            $request = $request->all();
            $filter_params = [
                'id', 'full_name', 'mobile', 'apply_id', 'car_id', 'risk_start_name', 'erp_credit_status', 'sales_type','status','start_time','end_time','id_card_num','seat_id'
            ];
            $request['pagesize'] = !empty($request['limit']) ? $request['limit'] : 5;
            $params = [];
            foreach ($filter_params as $key => $item) {
                if (!empty($request[$item])) {
                    if($item == 'start_time'){
                        $params['visa_time >='] = strtotime($request[$item]);
                    }else if($item == 'end_time'){
                        $params['visa_time <='] = strtotime($request[$item]);
                    }else{
                        $params[$item] = $request[$item];
                    }
                }
            }
            if(!empty($request['uid']) && empty($params)){
                $params = ['user_id' => intval($request['uid'])];
            }
            //获取渠道类型
            $sales_type = CarHalfService::purchase_map();
            $seatList = $seat_manager_obj->getSeatIdToFullNameArr();
            $statusMap = FastVisa::$visaStatusChineseMap;
            $city = new CityRepository();
            $city_list = $city->getAllCity(['cityid', 'cityname']); //城市列表
            $visaModel = new FastVisa();
            $count = $visaModel->countBy($params);
            $flag = session('uinfo.flag');
            $list = $visaModel->getList(['*'], $params, ['visa_time' => 'desc'], [], $request['pagesize']);
            foreach ($list as $k=>$v) {
                $v['line_up_time'] = !empty($v['line_up_time'])? date("Y-m-d H:i",$v['line_up_time']):"";
                $v['erp_credit_status'] = isset(FastVisa::$visaErpStatusChineseMap[$v['erp_credit_status']])? FastVisa::$visaErpStatusChineseMap[$v['erp_credit_status']]:'未知';
                $v['sales_type'] = isset($sales_type[$v['sales_type']])? $sales_type[$v['sales_type']]:'未知';
                $v['is_can_assign'] = in_array($v['status'],FastVisa::$canAssignStatusList)? 1:0;
                $v['status'] = isset($statusMap[$v['status']])? $statusMap[$v['status']]:'未知';
                $v['seat_id'] = isset($seatList[$v['seat_id']])? $seatList[$v['seat_id']]:'未知';
                $v['car_city_id'] = isset($city_list[$v['car_city_id']]['cityname'])? $city_list[$v['car_city_id']]['cityname']:'未知';
                $v['risk_time'] =  !empty($v['risk_time'])? date("Y-m-d H:i",$v['risk_time']):"";
                $v['visa_time'] = !empty($v['visa_time'])? date("Y-m-d H:i",$v['visa_time']):"";
                $v['flag'] = !empty($flag)? $flag:3;
            }
//            if (is_production_env()) {
//                Log::info('visa列表搜索：' . json_encode($params) . '；信息：' . json_encode($list));
//            }
            $list = $list->toArray()['data'];
            return $this->_page_format($list,$count);
        }else{
            //获取渠道类型
            $sales_type = CarHalfService::purchase_map();
            return view('fast_visa.all_list', [
                'erp_credit_status' => FastVisa::$visaErpStatusChineseMap,
                'interview_status' => FastVisa::$visaStatusChineseMap,
                'sales_type' => $sales_type,
                'seat_effect_list' => $seat_effect_list
            ]);
        }

    }
    /**
     * ajax 进入远程面签、进入详情页
     * @param Request $request
     * @return mixed
     * @throws \Exception
     * @throws \Throwable
     */
    public function ajaxGetVisaInfo(Request $request) {
        $visaId = $request->input('visa_id', '');
        $type = intval($request->input('type', '')); //判断列表详情类型(1待信审列表 ,2全量订单列表, 3复议列表)
        if (empty($visaId)) {
            return $this->showMsg(self::CODE_FAIL, self::MSG_PARAMS, []);
        }
        $seat_id = session('uinfo.seat_id');
        if ($type == 3) {
            $online_status = (new SeatManage())->getOne(['work_status'],['id' => $seat_id]);
            if ($online_status == SeatManage::SEAT_WORK_STATUS_FREE && !$this->redis->zRem(config('common.auto_apply_seat_key'),$seat_id)) {
                return $this->showMsg(self::CODE_FAIL, '状态有误,请重试!', []);
            }
        }
        $city_reos = new CityRepository();
        $common = new Common();
        $bank_type = config('dict.bank_type');
        //信审处理状态
        $applyStatus = config('credit.applyStatus');
        $applyStatus = array_column($applyStatus, 'msg', 'code');
        //优信首付额度
        $bankXinCreditLimit = config('credit.bankXinCreditLimit');
        //首付比例
        $bankMinPayRatio = config('credit.bankMinPayRatio');
        //优信审批贷款年限
        $loanYear = config('credit.loanYear');
        //面签视频详情
        $visaModel = new FastVisa();
        $visaInfo = $visaModel->getOne(['*'], ['id' => $visaId]);
        if (!$visaInfo) {
            return $this->showMsg(self::CODE_FAIL, '找不到数据', []);
        }
        $applyId = $visaInfo['apply_id'];
        $visaInfo['status_name'] = FastVisa::$visaStatusChineseMap[$visaInfo['status']];
        $visaInfo['sales_type_info'] = (!empty($visaInfo['sales_type']) && CarHalfService::purchase_map($visaInfo['sales_type']))? CarHalfService::purchase_map($visaInfo['sales_type']):'未知';
        $erp_credit_status = FastVisa::$visaErpStatusChineseMap;
        $visaInfo['erp_credit_status_info'] = (!empty($visaInfo['erp_credit_status']) && !empty($erp_credit_status[$visaInfo['erp_credit_status']]))? $erp_credit_status[$visaInfo['erp_credit_status']]:'未知';
        $visaResultModel = new FastVisaResult(); //获取面签结果
        $visaResult = $visaResultModel->getOne(['*'],['visa_id' => $visaInfo['id'],'in' => ['reconsideration_status' => [0,1,2],'visa_status' => [5,6,7,8]]] ,['id'=>'desc']);
        if(!empty($visaResult['refuse_tag'])){
            $visaResult['refuse_tag'] = explode(',',$visaResult['refuse_tag']);
            foreach ($visaResult['refuse_tag'] as $item){
                if(isset(FastVisa::$visa_refuse_category[$item])){
                    $refuse_tag[] = FastVisa::$visa_refuse_category[$item];
                }
            }
            $visaInfo['refuse_tag'] = implode(',',$refuse_tag);
        }
        $visaInfo['inside_opinion'] = '';
        $visaInfo['out_opinion'] = '';
        if (!empty($visaResult) && in_array($visaInfo['status'],[
                FastVisa::VISA_STATUS_AGREE,
                FastVisa::VISA_STATUS_REFUSE,
                FastVisa::VISA_STATUS_SKIP,
                FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT
                ])) {
            $visaInfo['inside_opinion'] = $visaResult['inside_opinion'];
            $visaInfo['out_opinion'] = $visaResult['out_opinion'];
            $visaInfo['need_verify'] = $visaResult['need_verify'];
            if ($type != 1) {
                $visaInfo['first_status_name'] = FastVisa::$visaStatusChineseMap[$visaResult['visa_status']];
            }
        }
        #有复议结果
        $recons_res_arr = [FastVisa::VISA_RECONSIDERATION_STATUS_PASS,FastVisa::VISA_RECONSIDERATION_STATUS_REFUSE,FastVisa::VISA_RECONSIDERATION_STATUS_OVERRULE];
        if(in_array($visaInfo['reconsideration_status'],$recons_res_arr)){
            $visaInfo['reconsideration_status_desc'] = FastVisa::$visa_reconsideration_map[$visaInfo['reconsideration_status']];
            $visaReconsResult = $visaResultModel->getOne(['*'],
                ['visa_id' => $visaInfo['id'],
                'in' => ['reconsideration_status' =>$recons_res_arr]
                ] ,['id'=>'desc']);
            if(!empty($visaReconsResult['refuse_tag'])){
                $visaReconsResult['refuse_tag'] = explode(',',$visaReconsResult['refuse_tag']);
                foreach ($visaReconsResult['refuse_tag'] as $item){
                    if(isset(FastVisa::$visa_refuse_category[$item])){
                        $refuse[] = FastVisa::$visa_refuse_category[$item];
                    }
                }
                $visaInfo['recons_refuse_tag'] = !empty($refuse)? implode(',',$refuse):'';
            }
            if (!empty($visaReconsResult)) {
                $visaInfo['recons_inside_opinion'] = $visaReconsResult['inside_opinion'];
                $visaInfo['recons_out_opinion'] = $visaReconsResult['out_opinion'];
            }
        }
        //微众人脸识别结果
        $wzFaceResult = (new FastVisaWzFaceResult())->getWzFaceResult($visaId);
        if ($wzFaceResult) {
            $wzFaceResult = isset(WzFace::$wzRecognizeStatusMap[$wzFaceResult]) ? WzFace::$wzRecognizeStatusMap[$wzFaceResult] : '未知结果'.$wzFaceResult;
        } else {
            $wzFaceResult = '未操作';
        }

        //获取数据
        $data = (new CommonRepository())->getVisaDetail($visaInfo);
        $code = isset($data['code']) ? $data['code'] : self::CODE_FAIL;
        $msg = isset($data['msg']) ? $data['msg'] : self::MSG_FAIL;
        $info = isset($data['data']) ? $data['data'] : [];
        if ($code != self::CODE_SUCCESS || !$info) {
            return $this->showMsg(self::CODE_FAIL, $msg, []);
        }
        //订单
        $car_half_apply = $info['apply_info'];
        //承租人信息
        $user_info = $info['user_info'];
        if(!empty($user_info['id_card_num'])){
            $id_card_count = $visaModel->countBy(['id_card_num' => trim($user_info['id_card_num'])]);
            $user_info['id_card_num'] =  $id_card_count > 1? $user_info['id_card_num'].'(有记录)':$user_info['id_card_num'];
        }
        //信审apply_Id
        $credit_apply_id = $info['credit_apply_id'];
        $fish_back_credit_id = !empty($visaInfo['credit_apply_id'])? $visaInfo['credit_apply_id']:$credit_apply_id;
        //捞回方式
        $fish_back = CommonRepository::get_fishing_back([$fish_back_credit_id]);
        /***首付与合同****/
        //首付款核销明细区
        $first_pay_info = $info['pay_ment_info'];
        //合同区
        $contract_info = $info['contract_info'];
        //超融明细
        $beyond_info = $info['beyond_info'];
        //贴息信息
        $discount_info = $info['discount_info'];
        //经销商信息
        $dealer_info = $info['dealer_info'];
        //车辆信息
        $car_info = $info['car_info'];
        if(!empty($car_info['vin'])){
            $vin_arr = $visaModel->getAll(['car_id'],['vin' => trim($car_info['vin'])]);
            if($vin_arr){
                $vin_str = implode(',',array_diff(array_unique(array_column($vin_arr,'car_id')),[$car_info['carid']]));
            }
            $car_info['vin'] =  !empty($vin_str)? $car_info['vin']."(有记录,历史车辆ID:{$vin_str})":$car_info['vin'];
        }
        //获取vin码对应的车型
        $vinCarInfo = $info['vin_car_info'];
        //刷卡总笔数
        $payLogCount = $info['paylog_count'];
        //补充信息
        $supplement_info = $info['supplement_info'];
        //补录信息
        $loanin_sign_mess_info = $info['loanin_sign_mess_info'];
        //历史审批
        $visaHistory = (new FastVisaResult())->getHistoryResultByVisaId($visaId);
        //审批记录
//        $remark_info = $info['remark_info'];
        //征信报告
        $creditReport = (new CreditRepository())->getCreditDetailByApplyId($credit_apply_id);
        //决策引擎信审
//        $decision_info = isset($info['decision_info']['response_data']) ? json_decode($info['decision_info']['response_data'], true) : '';
        //获取业务类型
        $business_type = $common->getAllProductScheme();
        //获取用户城市
        $city_name = $city_reos->getCityInfoByid($car_half_apply['cityid']);
        //资金渠道 微众 or 新网 or 稠州
        $bank_name = isset($bank_type[$visaInfo['channel']]) ? $bank_type[$visaInfo['channel']] : '';
        //超融编码(有超融月供编码)
        $sfProductCode = config('common.sfProductCode');
        //智能面签审核状态
        $visa_status = config('dict.visa_status');
        //智能面签结果状态
        $visa_result_status = config('dict.visa_result_status');
        /*********新网人脸识别 start***********/
        //如果是新网资方，则获取人脸识别结果
        $xw_face = '未进行';
        $faceFileUrl = '';
        if ($visaInfo['channel'] == self::FUND_CHANNEL_XINWANG) {
            $face_model = new FaceRecognitionRepository();
            $face = $face_model->getInfoByCondition(['status','file_url'], ['applyid' => $applyId]);

            $faceFileUrl = isset($face['file_url']) ?  'http://c1.xinstatic.com/'. $face['file_url'] : '';
            $status = isset($face['status']) ? $face['status'] : '';
            $status_list = config('dict.xinwang_face_ret');
            $xw_face = isset($status_list[$status]) ? $status_list[$status] : '';
        }
        /*********新网人脸识别 end***********/
        //通过masterId 获取销售im 账号
        $netAccount = '';
        $imAccount = (new ImRbacMaster())->select('im_account_id')->where('masterid', $visaInfo['master_id'])->first();
        if (!empty($imAccount)) {
            $imAccount = $imAccount->toArray();
            $r = (new Imaccount())->select('accid')->where('id',$imAccount['im_account_id'])->first();
            if ($r) {
                $r = $r->toArray();
                $netAccount = $r['accid'];
            }
        }
        //修改面签状态和坐席状态
        if ($type == 1) {
            if ($visaInfo['seat_id'] != 0 && $visaInfo['seat_id'] != session('uinfo.seat_id') &&  $visaInfo['status'] == FastVisa::VISA_STATUS_HANG_QUEUEING) {
                return $this->showMsg(self::CODE_FAIL, '数据异常，该面签已有坐席。');
            }
            $ret = (new FaceAuthRepository())->grabOrder($visaId);
            if ($ret['code'] != 1) {
                return $this->showMsg($ret['code'], $ret['msg']);
            }
        }elseif($type == 3){
            if (!in_array($visaInfo['reconsideration_status'],[ FastVisa::VISA_RECONSIDERATION_STATUS_CAN,FastVisa::VISA_RECONSIDERATION_STATUS_DOING])) {
                return $this->showMsg(self::CODE_FAIL, '数据异常，该订单复议状态有误!');
            }
            $ret = (new FaceAuthRepository())->grabOrder($visaId,$type);
            if ($ret['code'] != 1) {
                return $this->showMsg($ret['code'], $ret['msg']);
            }
        }
        //视频url
        $videoUrl = (new FastVideoData())->getVideoUrlByVisaIdAll($visaId);
        //是否存在信用关系网
        $relation_count = CreditApi::get_relation_count($user_info['uid']);
        //审批历史 调api
        $new_credit = CreditApi::get_new_credit_detail($credit_apply_id);
        //拒绝分类
        $refuse_category = FastVisa::$visa_refuse_category;
        foreach ($refuse_category as $k => $v) {
            if(in_array($k,[6])){
                unset($refuse_category[$k]);
            }
        }
        $extendObj = new FastVisaExtend();
        $material = $extendObj->getAll(['data','is_reconsideration'],['visa_id' => $visaInfo['id'],'type' => 0]);
        $material_count = [];
        if(!empty($material)){
            foreach ($material as $index => $data) {
                $d_temp = json_decode($data['data'],true);
                foreach ($d_temp as $ii => $vv ){
                    $material_count[$data['is_reconsideration']] += count($vv);
                }
            }
        }
        //区域面签发起城市
        $visaInfo['car_city_id_desc'] = $city_reos->getCityInfoByid($visaInfo['car_city_id']);
        //销售操作记录数
        $rbac_master_comment_count = (new RbacMasterComment())->countBy(['master_id' => $visaInfo['master_id']]);

        $data_all = [
            'view_type' => $type == 1 ? 'video':($type == 2?  'detail':'recons'),  //详情页类型，video：远程面签， detail:详情页
            'business_type' => $business_type,
            'visa_info' => $visaInfo,
            'car_half_apply' => $car_half_apply,
            'user_info' => $user_info,
            'city_name' => $city_name,
            'bank_name' => $bank_name,
            'xw_face' => $xw_face,
            'face_file_url' => $faceFileUrl,
            'first_pay_info' => $first_pay_info,
            'credit_report' => $creditReport,
            'sfProductCode' => $sfProductCode,
            'contract_info' => $contract_info,
            'beyond_info' => $beyond_info,
            'discount_info' => $discount_info,
            'dealer_info' => $dealer_info,
            'car_info' => $car_info,
            'vinCarInfo' => $vinCarInfo,
            'pay_log_count' => $payLogCount,
            'supplement_info' => $supplement_info,
            'loanin_sign_mess_info' => $loanin_sign_mess_info,
            'visa_result_status' => $visa_result_status,
            'viusa_check_list' => $visaHistory,
            'visa_status' => $visa_status,
//            'decision_info' => $decision_info,
//            'remark_info' => $remark_info,
            'apply_status' => $applyStatus,
            'bankXinCreditLimit' => $bankXinCreditLimit,
            'bankMinPayRatio' => $bankMinPayRatio,
            'loanYear' => $loanYear,
            'visa_id' => $visaId,
            'wz_face_result' => $wzFaceResult,
            'im_account_id'=>$netAccount,
            'video_url' => json_encode($videoUrl),
            'relation_count' => $relation_count,
//            'credit_apply_id' => $credit_apply_id,
            'refuse_category' => $refuse_category,
            'new_credit' => !empty($new_credit[$credit_apply_id])? $new_credit[$credit_apply_id]:[],
            'fish_back' => empty($fish_back[$visaInfo['user_id']])? []:$fish_back[$visaInfo['user_id']],
            'rbac_master_comment_count' => $rbac_master_comment_count,
            'material_count' => $material_count
        ];
        $content = trim(view('fast_visa.visa_info', $data_all)->render());
        return $this->showMsg(1, 'OK', $content);
    }
    private function formatParamList($request)
    {
        //接收参数
        $status = isset($request['status']) ? $request['status'] : 0;
        $applyid = isset($request['applyid']) ? $request['applyid'] : 0;
        $fullname = isset($request['fullname']) ? $request['fullname'] : '';
        $mobile = isset($request['mobile']) ? $request['mobile'] : '';
        $id_card_num = isset($request['id_card_num']) ? $request['id_card_num'] : '';
        $carid = isset($request['carid']) ? $request['carid'] : '';
        $channel = isset($request['channel']) ? $request['channel'] : 0;
        $business_type = isset($request['business_type']) ? $request['business_type'] : '';
        $risk_start_name = isset($request['risk_start_name']) ? $request['risk_start_name'] : '';
        $car_cityid = isset($request['car_cityid']) ? $request['car_cityid'] : '';
        $risk_at = isset($request['risk_at']) ? $request['risk_at'] : '';
        $erp_credit_status = isset($request['erp_credit_status']) ? $request['erp_credit_status'] : '';
        $sales_type = isset($request['sales_type']) ? $request['sales_type'] : '';
        $id = isset($request['id']) ? $request['id'] : '';
        $params = [];
        if ($status) $params['status'] = $status;
        if ($applyid) $params['apply_id'] = $applyid;
        if ($fullname) $params['full_name'] = $fullname;
        if ($mobile) $params['mobile'] = $mobile;
        if ($id_card_num) $params['id_card_num'] = $id_card_num;
        if ($carid) $params['car_id'] = $carid;
        if ($channel) $params['channel'] = $channel;
        if ($business_type) $params['business_type'] = $business_type;
        if ($risk_start_name) $params['risk_start_name'] = $risk_start_name;
        if ($car_cityid) $params['car_city_id'] = $car_cityid;
        if ($risk_at) $params['risk_time'] = $risk_at;
        if ($erp_credit_status) $params['erp_credit_status'] = $erp_credit_status;
        if ($sales_type) $params['sales_type'] = $sales_type;
        if ($id) $params['id'] = $id;

        return $params;
    }

    private function formatHangListParamList($request)
    {
        //接收参数
        $status = isset($request['hang_list_status']) ? $request['hang_list_status'] : 0;
        $applyid = isset($request['hang_list_applyid']) ? $request['hang_list_applyid'] : 0;
        $fullname = isset($request['hang_list_fullname']) ? $request['hang_list_fullname'] : '';
        $mobile = isset($request['hang_list_mobile']) ? $request['hang_list_mobile'] : '';
        $carid = isset($request['hang_list_carid']) ? $request['hang_list_carid'] : '';
        $channel = isset($request['hang_list_channel']) ? $request['hang_list_channel'] : 0;
        $business_type = isset($request['hang_list_business_type']) ? $request['hang_list_business_type'] : '';
        $risk_start_name = isset($request['hang_list_risk_start_name']) ? $request['hang_list_risk_start_name'] : '';
        $car_cityid = isset($request['hang_list_car_cityid']) ? $request['hang_list_car_cityid'] : '';
        $risk_at = isset($request['hang_list_risk_at']) ? $request['hang_list_risk_at'] : '';
        $id = isset($request['id']) ? $request['id'] : '';

        $params = [];
        if ($status) $params['status'] = $status;
        if ($applyid) $params['apply_id'] = $applyid;
        if ($fullname) $params['full_name'] = $fullname;
        if ($mobile) $params['mobile'] = $mobile;
        if ($carid) $params['car_id'] = $carid;
        if ($channel) $params['channel'] = $channel;
        if ($business_type) $params['business_type'] = $business_type;
        if ($risk_start_name) $params['risk_start_name'] = $risk_start_name;
        if ($car_cityid) $params['car_city_id'] = $car_cityid;
        if ($risk_at) $params['risk_time'] = $risk_at;
        if ($id) $params['id'] = $id;
        return $params;
    }


    /*
     * 坐席统计-审批明细列表
     */
    public function visaList(Request $request){

        $request = $request->all();
        //接收参数
        $request['pagesize'] = !empty($request['pagesize']) ? $request['pagesize'] : 5;
        $start_time = isset($request['start_time']) ? $request['start_time'] : 0;
        $end_time = isset($request['end_time']) ? $request['end_time'].' 23:59:59' : 0;
        $status = isset($request['status']) ? $request['status'] : 0;
        $applyid = isset($request['applyid']) ? $request['applyid'] : 0;
        $fullname = isset($request['fullname']) ? $request['fullname'] : '';
        $mobile = isset($request['mobile']) ? $request['mobile'] : '';
        $carid = isset($request['carid']) ? $request['carid'] : '';
        $channel = isset($request['channel']) ? $request['channel'] : 0;
        $business_type = isset($request['business_type']) ? $request['business_type'] : '';

        $risk_start_name = isset($request['risk_start_name']) ? $request['risk_start_name'] : '';
        $car_cityid = isset($request['car_cityid']) ? $request['car_cityid'] : '';
        $risk_at = isset($request['risk_at']) ? $request['risk_at'] : '';

        $status_limit = [ FastVisa::VISA_STATUS_AGREE,FastVisa::VISA_STATUS_REFUSE,FastVisa::VISA_STATUS_SKIP];
        $params = [];

        //该列表普通用户和坐席只能看到自己的单子
        if (UserRepository::isGuest() || UserRepository::isSeat()) {
            $params['fast_visa.seat_id'] = session('uinfo.seat_id');
        }
        //该列表只展示通过，拒绝和跳过的(567),
        if ($status) {
            $params['fast_visa.status'] = $status;
        }else{
            $params['in']['fast_visa.status'] = $status_limit;
        }
        if ($applyid) {
            $params['fast_visa.apply_id'] = $applyid;
        }
        if ($fullname) {
            $params['full_name'] = $fullname;
        }
        if ($mobile) {
            $params['mobile'] = $mobile;
        }
        if ($carid) {
            $params['car_id'] = $carid;
        }
        if ($channel) {
            $params['channel'] = $channel;
        }
        if ($start_time) {
            $params['fast_visa.created_at >='] = $start_time;
        }
        if ($end_time) {
            $params['fast_visa.created_at <='] = $end_time;
        }

        if ($risk_start_name) {
            $params['risk_start_name'] = $risk_start_name;
        }

        if ($risk_start_name) {
            $params['risk_start_name'] = $risk_start_name;
        }

        //获取fast_via
        $fastVisaModel = new FastVisa();
        $query = $fastVisaModel->selectRaw('fast_visa.*');
        $query = $fastVisaModel->createWhere($query, $params, ['fast_visa.id'=>'desc']);
        $list = $query->paginate($request['pagesize']);
        $list->setPath('');
        $visaIds = [];
        foreach ($list as $each) {
            $visaIds[] = $each->id;
        }

        //获取每个fast_visa_id最新的一条fast_visa_log
        $fastVisaLogList = [];
        $fastVisaLogListTmp = (new FastVisaLog())->getAll(['*'], ['in'=>['visa_id'=>$visaIds]], [], [], true);
        foreach($fastVisaLogListTmp as $each){
            if (!isset($fastVisaLogList[$each['visa_id']]) || ($fastVisaLogList[$each['visa_id']]['id'] < $each['id'])) {
                $fastVisaLogList[$each['visa_id']] = $each;
            }
        }
        unset($fastVisaLogListTmp);

        //fast_visa 和 fast_visa_log建立连接
        foreach ($list as &$each) {
            if (isset($fastVisaLogList[$each['id']])) {
                $each->visa_id = $fastVisaLogList[$each['id']]['visa_id'];
                $each->queuing_time = $fastVisaLogList[$each['id']]['queuing_time'];
                $each->seat_receive_time = $fastVisaLogList[$each['id']]['seat_receive_time'];
                $each->call_video_time = $fastVisaLogList[$each['id']]['call_video_time'];
                $each->end_video_time = $fastVisaLogList[$each['id']]['end_video_time'];
                $each->visa_time = $fastVisaLogList[$each['id']]['visa_time'];
            } else {
                $each->visa_id = 0;
                $each->queuing_time = 0;
                $each->seat_receive_time = 0;
                $each->call_video_time = 0;
                $each->end_video_time = 0;
                $each->visa_time = 0;
            }
        }
        unset($fastVisaLogList);

        $common = new Common();
        //获取业务类型
        $business_type = $common->getAllProductScheme();

        $bank_type = config('dict.bank_type');
        //城市列表
        $city = new CityRepository();
        $city_list = $city->getAllCity(['cityid','cityname']);
        //状态
        $status_list_all = config('dict.visa_status');

        foreach ($status_limit as $status){
            $visa_status[$status] = $status_list_all[$status];
        }

        $seatList = (new SeatManage())->getSeatIdToFullNameArr();

        $channel = config('dict.bank_type');

        return view('fast_visa.visa_list', [
            'request' => $request,
            'list' => $list,
            'visa_status' => $visa_status,
            'channel' => $channel,
            'business_type' => $business_type,
            'city_list' => $city_list,
            'bank_type' => $bank_type,
            'seat_list' => $seatList
        ]);
    }

    /**
     * 坐席统计-审核明细-导出
     * @param Request $request
     */
    public function export(Request $request)
    {
        $start_time = isset($request['start_time']) ? $request['start_time'] : 0;
        $end_time = isset($request['end_time']) ? $request['end_time'].' 23:59:59' : 0;
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $fileName = "审批明细-" . date("Ymd_His") . ".csv";
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);
        $export_arr = [
            "ID","处理坐席","面签状态","拒绝标记","放款编号","面签承租人","注册手机","车辆名称","车辆ID","资金渠道","业务类型",
            "用户触发排队时间","坐席领取用户时间","面签结果批复时间","身份证号","区域面签发起城市","区域面签发起人","面签内部意见",
            "面签外部意见","创建时间","信审捞回","租赁期数","提交人员姓名","提交城市","渠道类型","需要电核",
            "车商车价","融资总额","操作建议","拒绝码","规则详细内容1","规则详细内容3"
        ];
        echo iconv("UTF-8",
                "gbk",
                implode(',',$export_arr)) . "\n";
        $params = [];
        if ($start_time) {
            $params['fast_visa.visa_time >='] = strtotime($start_time);
        }
        if ($end_time) {
            $params['fast_visa.visa_time <='] = strtotime($end_time);
        }
        $fastVisaModel = new FastVisa();
        $fields = "fast_visa.*,
        SUBSTRING_INDEX(GROUP_CONCAT(log.queuing_time ORDER BY log.updated_at DESC),',',1) AS queuing_time,
        SUBSTRING_INDEX(GROUP_CONCAT(log.seat_receive_time ORDER BY log.updated_at DESC),',',1) AS seat_receive_time,
        SUBSTRING_INDEX(GROUP_CONCAT(log.visa_time ORDER BY log.updated_at DESC),',',1) AS visa_time,
        SUBSTRING_INDEX(GROUP_CONCAT(res.inside_opinion ORDER BY log.updated_at DESC),',',1) AS inside_opinion,
        SUBSTRING_INDEX(GROUP_CONCAT(res.out_opinion ORDER BY log.updated_at DESC),',',1) AS out_opinion,
        SUBSTRING_INDEX(GROUP_CONCAT(res.refuse_tag ORDER BY log.updated_at DESC separator '_'),'_',1) AS refuse_desc,
        SUBSTRING_INDEX(GROUP_CONCAT(res.need_verify ORDER BY log.updated_at DESC separator '_'),'_',1) AS need_verify
        ";
        $query = $fastVisaModel->selectRaw($fields)
            ->leftJoin('fast_visa_log as log','fast_visa.id','=','log.visa_id')
            ->leftJoin('fast_visa_result as res','fast_visa.id','=','res.visa_id');
        $query = $fastVisaModel->createWhere($query, $params, ['fast_visa.id'=>'desc'],['fast_visa.id']);
        $query->chunk(300,function($list){
            $list = $this->formatVisaList($list);
            foreach ($list as $k => $row){
                $row_arr = [];
                $row_arr['id'] =  $row->id;//visa_ID
                $row_arr['seat_name'] =  $row->seat_name;//处理坐席 处理坐席姓名（修复）
                $row_arr['status_desc'] =  $row->status_desc;//面签状态
                $row_arr['refuse_desc'] =  $row->refuse_desc;//面签状态
                $row_arr['apply_id'] =  $row->apply_id;//放款编号
                $row_arr['full_name'] =  $row->full_name;//面签承租人
                $row_arr['mobile'] =  $row->mobile;//注册手机
                $row_arr['car_name'] =  $row->car_name;//车辆名称
                $row_arr['car_id'] =  $row->car_id;//车辆ID
                $row_arr['channel_desc'] =  $row->channel_desc;//资金渠道
                $row_arr['business_type'] =  $row->business_type;//业务类型 业务类型（修复）
                $row_arr['queuing_time'] =  $row->queuing_time;//用户触发排队时间
                $row_arr['seat_receive_time'] =  $row->seat_receive_time;//坐席领取用户时间
                $row_arr['visa_time'] =  $row->visa_time;//面签结果批复时间
                $row_arr['id_card_num'] =  $row ->id_card_num;//身份证号
                $row_arr['car_city_id'] =  $row->car_city_id;//区域面签发起城市
                $row_arr['risk_start_name'] =  $row->risk_start_name;//区域面签发起人
                $row_arr['inside_opinion'] =  $row->inside_opinion;//面签内部意见
                $row_arr['out_opinion'] =  $row->out_opinion;//面签外部意见
                $row_arr['created_at'] =  $row->created_at;//创建时间
                $row_arr['fishing_back'] =  $row->fishing_back;//捞回方式
                $row_arr['loan_term'] =  $row->loan_term;//租赁期数
                $row_arr['sale_name'] =  $row->sale_name;//提交人员姓名
                $row_arr['sale_city'] =  $row->sale_city;//提交城市
                $row_arr['sales_type'] =  $row->sales_type;//渠道类型
                $row_arr['need_verify'] =  $row->need_verify;//需要电核
                //第九版新增
                $row_arr['dealerPrice'] =  $row->dealerPrice;//车商车价
                $row_arr['totalRaisedFund'] =  $row->totalRaisedFund;//融资总额
                $row_arr['operate_advice'] =  $row->operate_advice;//操作建议
                $row_arr['refuse_code'] =  $row->refuse_code;//拒绝码
                $row_arr['rule_info_1'] =  $row->rule_info_1;//规则详细内容1
                $row_arr['rule_info_3'] =  $row->rule_info_3;//规则详细内容3
                if(!empty($row_arr)){
                    echo iconv("UTF-8", "gbk//TRANSLIT", '"' . implode('","', $row_arr)) . "\"\n";
                }
            }
        });

        exit;

    }


    private function formatVisaList($list){
        if(empty($list)) {
            return false;
        }
        $common = new Common();
        //获取业务类型
        $business_type = $common->getAllProductScheme();
        //获取身份证
        $list_arr = json_decode(json_encode($list),true);
        $apply_arr = array_column($list_arr,'apply_id');
        $credit_apply_id =  array_column($list_arr,'credit_apply_id');
        $master_id_arr = array_column($list_arr,'master_id');
        $car_half_apply = new CarHalfApply();
        $id_card_arr = $car_half_apply->getAll(['applyid','id_card_num'],['in'=>['applyid'=>$apply_arr]]);
        $id_card_arr = array_column($id_card_arr,null,'applyid');
        $fish_back = CommonRepository::get_fishing_back($credit_apply_id);
        $loan_terms = CommonRepository::getLoanTerm($apply_arr);
        $saleNameCity = CommonRepository::getSaleNameCity($apply_arr);
        $apply_id_str = implode(',',array_filter(array_unique($apply_arr)));
        $credit_apply_id_str = implode(',',array_filter(array_unique($credit_apply_id)));
        #车商车价
        $order_fee = ErpApi::getCarFee($apply_id_str);
        #信审信息
        $credit_info = CreditApi::get_new_credit_detail($credit_apply_id_str);
        $master_name_arr = (new RbacMaster())->getAll(["masterid","fullname","mobile"],['in' => ['masterid' => $master_id_arr]]);
        $master_name_arr = array_column($master_name_arr,null,'masterid');
        //城市列表
        $city = new CityRepository();
        $city_list = $city->getAllCity(['cityid','cityname']);
        //坐席id与姓名
        $seatList = (new SeatManage())->get_export_name();
        $visa_status = config('dict.visa_status');
        $channel = config('dict.bank_type');

        foreach ($list as $key=> $info) {
            $list[$key]['status_desc'] = isset($visa_status[$info['status']]) ? $visa_status[$info['status']] : '未知';
            $list[$key]['channel_desc'] = isset($channel[$info['channel']]) ? $channel[$info['channel']] : '未知';
            $list[$key]['business_type'] = isset($business_type['0_'. $info['business_type'].'_'.$info['channel_type']]) ? $business_type['0_'. $info['business_type'].'_'.$info['channel_type']] : '未知';
            $list[$key]['queuing_time'] = date('Y-m-d H:i:s', $info['line_up_time']);
            $list[$key]['seat_receive_time'] = date('Y-m-d H:i:s', $info['seat_receive_time']);
            $list[$key]['call_video_time'] = date('Y-m-d H:i:s', $info['call_video_time']);
            $list[$key]['end_video_time'] = date('Y-m-d H:i:s', $info['end_video_time']);
            $list[$key]['visa_time'] = date('Y-m-d H:i:s', $info['visa_time']);
            $list[$key]['mobile'] = substr_replace($info['mobile'], '****', 3, 4);
            $list[$key]['car_city_id'] = isset($city_list[$info['car_city_id']])? $city_list[$info['car_city_id']]['cityname']:'未知';
            $list[$key]['id_card_num'] =  isset($id_card_arr[$info['apply_id']]['id_card_num'])? substr_replace($id_card_arr[$info['apply_id']]['id_card_num'], '****', 6, 4):'未知';//身份证号
            $list[$key]['seat_name'] = !empty($info['seat_name']) ? $info['seat_name'] : (!empty($info['seat_id'])? $seatList[$info['seat_id']]:'未知');
            $list[$key]['refuse_desc'] = call_user_func(function ($v) {
                if(!empty($v)){
                    $v = explode(',',$v);
                    foreach ($v as $val) {
                        $arr[] =  FastVisa::$visa_refuse_category[$val];
                    }
                    $v = null;
                    return implode(',',$arr);
                }
                return '';
            },$info['refuse_desc']);
            $list[$key]['fishing_back'] = !empty($fish_back[$info['user_id']])? $fish_back[$info['user_id']]:'';
            $list[$key]['loan_term'] =  !empty($loan_terms[$info['apply_id']])? $loan_terms[$info['apply_id']]:'';
            $list[$key]['sale_name'] =  !empty($master_name_arr[$info['master_id']]['fullname'])? $master_name_arr[$info['master_id']]['fullname']:'';
            $list[$key]['sale_city'] =  !empty($saleNameCity[$info['apply_id']]['ip_city_name'])? $saleNameCity[$info['apply_id']]['ip_city_name']: $list[$key]['car_city_id'] ;
            $list[$key]['sales_type'] = !empty($info['sales_type'])? CarHalfService::purchase_map(intval($info['sales_type'])):"";
            $list[$key]['need_verify'] =  !empty($info['need_verify'])? "是":"";
            #车商车价
            $list[$key]['dealerPrice'] =  !empty($order_fee[$info['apply_id']])? $order_fee[$info['apply_id']]['car_price_settlement']:0;
            #融资总额
            $list[$key]['totalRaisedFund'] =  !empty($order_fee[$info['apply_id']])? $order_fee[$info['apply_id']]['total_loan']+$order_fee[$info['apply_id']]['sf_total_loan']:0;
            #操作建议
            $list[$key]['operate_advice'] =  !empty($credit_info[$info['credit_apply_id']])? $credit_info[$info['credit_apply_id']]['decision']['operate_advice']:'';
            #拒绝码
            $list[$key]['refuse_code'] =  call_user_func_array(function($param){
                if ($param && !empty($param['remarks'])) {
                    $keys = [
                        'refuse1','refuse2','refuse3'
                    ];
                    $refuse_code = [];
                    foreach ($param['remarks'] as $key => $item) {
                       foreach ($keys as $val) {
                           if (!empty($item[$val])) {
                               $refuse_code[] = $item[$val];
                           }
                       }
                    }
                    if ($refuse_code) {
                        return implode(',',$refuse_code);
                    }
                }
                return '';
            },[$credit_info[$info['credit_apply_id']]]);
            #规则详情内容1
            $list[$key]['rule_info_1'] =  !empty($credit_info[$info['credit_apply_id']]['decision']['sos_response']['rule_detailContent1'])? strip_tags($credit_info[$info['credit_apply_id']]['decision']['sos_response']['rule_detailContent1']):'';
            #规则详细内容3
            $list[$key]['rule_info_3'] =  !empty($credit_info[$info['credit_apply_id']]['decision']['sos_response']['rule_detailContent3'])? strip_tags($credit_info[$info['credit_apply_id']]['decision']['sos_response']['rule_detailContent3']):'';
        }

        return $list;

    }

    /**
     * 挂起或挂起排队状态置为重新排队
     * @param mixed $request
     */
    public function restatus(Request $request)
    {
        $visa_id = intval(trim($request->input('visa_id',0)));
        if(empty($visa_id))
        {
            return $this->showMsg(self::CODE_FAIL,self::MSG_PARAMS);
        }
        //事务提交
        DB::connection('mysql.video_visa')->beginTransaction();
        try
        {
            #修改状态
            $visa_model = new FastVisa();
            $visa_res = $visa_model->getOne(['id','status','master_id','seat_id','seat_name','line_up_time'],['id' => $visa_id]);
            if(empty($visa_res)){
                FastException::throwException('不存在这条数据!');
            }
            if($visa_res['status'] != FastVisa::VISA_STATUS_HANG){
                FastException::throwException('不能重置这条数据!');
            }
            $update_res = $visa_model->updateBy(['status' => FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT],['id' => $visa_id]);
            if (!$update_res) {
                FastException::throwException('重置状态失败!');
            }
            #增加log
            $visa_log_model = new FastVisaLog();
            $newVisaLogData = [
                'visa_id' => $visa_res['id'],
                'master_id' => $visa_res['master_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'queuing_time' => $visa_res['line_up_time'],
                'seat_id' => $visa_res['seat_id'],
                'visa_time' => time(),
                'visa_status' => FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT
            ];
            $newLogId = $visa_log_model->insertVisaLog($newVisaLogData);
            if (!$newLogId) {
                FastException::throwException('新增log数据失败，visaId:'. $visa_res['id']);
            }
            DB::connection('mysql.video_visa')->commit();
            return $this->showMsg(self::CODE_SUCCESS,'重置成功!');
        }catch (\Exception $e)
        {
            DB::connection('mysql.video_visa')->rollback();
            return $this->showMsg(self::CODE_FAIL,'重置失败!');
        }
    }

    /**
     * 管理员手动分配订单
     * @param Request $request
     * @return mixed
     */
    public function assign_order(Request $request)
    {
        $visa_id = $request->input('visa_id',0);
        $fullname = $request->input('fullname',0);
        if(empty($visa_id) || empty($fullname)){
            return $this->showMsg(self::CODE_FAIL,'操作有误!');
        }
        $seat_id = $request->input('seat_id',0);
        $seat_list = (new SeatManage())->get_effective_seat();
        if($request->isMethod('post')){
            if(empty($seat_id) || empty($visa_id)){
                return $this->showMsg(self::CODE_FAIL,'传递参数错误!');
            }
            if(!UserRepository::isAdmin() && !UserRepository::isRoot()){//不是管理员和超管
                return $this->showMsg(self::CODE_FAIL,'您没有权限!');
            }
            $visa_model = new FastVisa();
            $visa_info = $visa_model->getOne(['*'],['id' => (int)trim($visa_id)]);
            if(!in_array($visa_info['status'],FastVisa::$canAssignStatusList)){ //不处于可以分配的订单状态
                return $this->showMsg(self::CODE_FAIL,'订单在该状态下不能分配坐席!');
            }
            #可领取状态
            if($visa_info['status'] == FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK){
                #将此订单移除排队队列
                if(!$this->redis->zRem(config('common.auto_apply_order_key'),$visa_id)){
                    return $this->showMsg(self::CODE_FAIL,'从订单队列移除失败,此订单暂不能分配坐席!');
                }
            }
            if(in_array($visa_info['status'],[FastVisa::VISA_STATUS_NOT_IN_QUEUE,FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT,FastVisa::VISA_STATUS_HANG])){
                $status = FastVisa::VISA_STATUS_HANG;
            }else{
                $status = FastVisa::VISA_STATUS_HANG_QUEUEING;
            }
            $update_res = $visa_model->updateBy(
                [
                    'status'=>$status,
                    'seat_id' => $seat_id,
                    'seat_name' =>$seat_list[$seat_id],
                    'remark' => json_encode_new([
                        'seat_id' => session('uinfo.seat_id'),
                        'seat_name' => session('uinfo.fullname'),
                        'op_time' => date('Y-m-d H:i:s')
                    ])
                ],
                ['id' => $visa_id]);
            if($update_res){ //成功分配
                if(  $status == FastVisa::VISA_STATUS_HANG_QUEUEING){
                    #加入该坐席挂起排队队列
                    $this->redis->zadd(config('common.auto_apply_order_key'),$seat_id,$visa_id);
                }
                return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS);
            }
        }else{
            #查询有效坐席
            return view('fast_visa.assign_seat',array_merge(['seat_list' => $seat_list],['visa_id' => $visa_id,'fullname' => $fullname]));
        }

    }
    //跳转关系网
    public function openRelationHtml(Request $request) {
        $uid = $request->input('uid',0);
        if ($uid){
            $url = CreditApi::get_relation_detail(intval(trim($uid)));
            return  $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS,['url' => $url]);
        }

    }
    /**
     * 销售评论管理
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|mixed
     */
    public function rbacMasterComment(Request $request){
        $master_id = intval(trim($request->input('master_id',0)));
        $visa_id = intval(trim($request->input('visa_id',0)));
        $comment= trim($request->input('comment',''));
        if ($request->isMethod('POST')) {
            if(empty($master_id) || empty($visa_id) || empty($comment)){
                common::sendMail('销售评论出现错误',$master_id.'|'.$visa_id.'|'.$comment);
                return  $this->showMsg(self::CODE_FAIL,self::MSG_FAIL);
            }
            //增加记录
            $arr = [
                'visa_id' => $visa_id,
                'master_id' => $master_id,
                'master_name' => 'ceshi',
                'seat_id' => session('uinfo.seat_id'),
                'seat_name' => session('uinfo.fullname'),
                'remark' => $comment,
                'create_at' => time()
            ];

            $res = (new RbacMasterComment())->insert($arr);
            return  $res? $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS) : $this->showMsg(self::CODE_FAIL,self::MSG_FAIL);
        }else{
            if(empty($master_id) || empty($visa_id) ){
                throw  new Exception('出现异常!');
            }
            $res = (new RbacMasterComment())->getAll(['id','master_id','master_name','create_at','remark','seat_name'],['master_id' => $master_id]);
            if($res){
                foreach ($res as $k => $v) {
                    $res[$k]['create_at'] = date('Y-m-d H:i',$v['create_at']);
                }
            }
            return view('fast_visa.comment',[
                'res' => $res,
                'visa_id' => $visa_id,
                'master_id' => $master_id,
                'count' => count($res)
            ]);
        }
    }


}