<?php
namespace App\Http\Controllers;

use App\Fast\FastException;
use App\Fast\FastKey;
use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Library\Helper;
use App\Library\RedisCommon;
use App\Library\RedisObj;
use App\Library\HttpRequest;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaExtend;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\VisaPool;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\VisaRemark;
use App\Models\VideoVisa\VisaRemarkAttach;
use App\Models\XinCredit\CarHalfApplyCredit;
use App\Repositories\BankPayRepository;
use App\Repositories\CityRepository;
use App\Repositories\CommonRepository;
use App\Repositories\Face\WzFace;
use App\Repositories\FaceAuthRepository;
use App\Repositories\FaceRecognitionRepository;
use App\Repositories\FastVisaRepository;
use App\Repositories\SeatManageRepository;
use App\Repositories\Visa\VisaRepository;
use App\Repositories\VisaRemarkRepository;
use App\XinApi\AptService;
use App\XinApi\CommonApi;
use function foo\func;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\VideoVisa\ImRbacMaster;
use App\Models\VideoVisa\ImAccount as Imaccount;
use App\Repositories\CreditRepository;
use App\Models\XinFinance\CarLoanOrder;
/**
 * 公共方法
 */
class CommonController extends BaseController {

	//新网资方
	const FUND_CHANNEL_XINWANG = 10;
	public $redis = null;
	public $wz_face = null;
	protected $month_time;

	public function __construct() {
		$this->redis = new RedisCommon();
		$this->wz_face = new WzFace();
        for ($i = 5;$i>=0;$i--){
            $this->month_time[$i] = ltrim(date('m月',strtotime(date('Y-m') . " -$i month")),'0');
        }
	}

	//刷卡历史
	public function getPayHistory(Request $request) {
		$bank_no = $request->input('bank_no', '');
		$car_type = $request->input('car_type', '');
		if (empty($bank_no)) {
			return [];
		}
		// 通过银行卡获取刷卡历史
		$bankpay_pos = new BankPayRepository();
		$history_list = $bankpay_pos->getPayHistory($bank_no, $car_type);

		$car_list = isset($history_list['car_list']) ? $history_list['car_list'] : [];
		$bankpay_list = isset($history_list['bankpay_list']) ? $history_list['bankpay_list'] : [];
		$dealer_list = isset($history_list['dealer_list']) ? $history_list['dealer_list'] : [];
		$bank_bin = isset($history_list['bank_bin']) ? $history_list['bank_bin'] : [];
		$city_list = isset($history_list['city_list']) ? $history_list['city_list'] : [];

		$status = config('dict.clr_type');
		$finance_status = config('dict.finance_status');

		return $content = trim(view('fast_visa.payment_history', [
			'car_list' => $car_list,
			'bankpay_list' => $bankpay_list,
			'dealer_list' => $dealer_list,
			'bank_bin' => $bank_bin,
			'city_list' => $city_list,
			'status' => $status,
			'finance_status' => $finance_status,
		])->render());
	}

    /**
     * 审核动作
     * @param Request $request
     * @return mixed
     */
    public function authVisa(Request $request)
    {
        $visaId = $request->input('visa_id', '');
        $inside_opinion = $request->input('inside_opinion', '');
        $inside_opinion = strip_tags(trim($inside_opinion));
        $out_opinion = $request->input('out_opinion', '');
        $out_opinion = strip_tags(trim($out_opinion));
        $status = $request->input('status', 0);
        $refuse_category = $request->input('refuse_category', []);
        $go_on_hanld_apply = intval($request->input('go_on_hanld_apply', 0)); //是否继续接单
        $is_can_recons = intval($request->input('is_can_recons', 0)); //是否支持复议
        $need_verify = intval($request->input('need_verify', 0)); //是否需要电核
        $refuse_tag = [];
        //参数校验
        if (!$visaId) {
            return $this->showMsg(self::CODE_FAIL, 'visa_id不能为空');
        }
        if (!$inside_opinion || !$out_opinion) {
            return $this->showMsg(self::CODE_FAIL, '内部意见、外部意见不能为空');
        }
        //状态检测
        if (!in_array($status, [-2,5, 6, 7, 8, 10])) {
            return $this->showMsg(self::CODE_FAIL, self::MSG_FAIL);
        }
        if($status == FastVisa::VISA_STATUS_REFUSE && empty($status)){
            return $this->showMsg(self::CODE_FAIL, '审批结果为拒绝时,必须选择拒绝标记!');
        }
        if($status == FastVisa::VISA_RECONSIDERATION_STATUS_OVERRULE && empty($status)){
            return $this->showMsg(self::CODE_FAIL, '审批结果为复审驳回时,必须选择驳回标记!');
        }
        $visaObj = new FastVisa();
        $visaInfo = $visaObj->getVisaInfoById($visaId);
        if (empty($visaInfo)) return $this->showMsg(self::CODE_FAIL, '未找到数据记录！'.$visaId);
        $seatId = session('uinfo.seat_id');
        if ($seatId != $visaInfo['seat_id']) return $this->showMsg(self::CODE_FAIL, '该订单已被其他坐席人员领取，您无权审核！');
        //审核通过，审核拒绝必须要有视频记录
//        if (in_array($status, [FastVisa::VISA_STATUS_AGREE, FastVisa::VISA_STATUS_REFUSE]) && !$visaObj->isAlreadyVideo($visaId)) {
//            return $this->showMsg(self::CODE_FAIL, '审核通过、拒绝必须经过视频');
//        }
        $seatObj = new SeatManage();
        $tag_list = array_flip(array_keys(FastVisa::$visa_refuse_category));
        if($status == FastVisa::VISA_STATUS_REFUSE){
            foreach ($refuse_category as $item) {
                if(!empty($item) && isset($tag_list[$item])){
                    $refuse_tag[] = intval(trim($item));
                }
            }
        }
        //事务提交
        DB::connection('mysql.video_visa')->beginTransaction();
        try {
            $updateList['prev_seat_id'] = $visaInfo['seat_id'];
            $updateList['prev_seat_name'] = $visaInfo['seat_name'];
            $updateList['prev_risk_start_visa_time'] = $visaInfo['visa_time'];
            $updateList['visa_time'] = time();
            $updateList['seat_id'] = $seatId;
            $updateList['seat_name'] = session('uinfo.fullname');
            if ($visaInfo['reconsideration_status'] == FastVisa::VISA_RECONSIDERATION_STATUS_DOING) {
                $updateList['reconsideration_status'] = $status == FastVisa::VISA_STATUS_AGREE ? FastVisa::VISA_RECONSIDERATION_STATUS_PASS : FastVisa::VISA_RECONSIDERATION_STATUS_REFUSE;
//                $status = FastVisa::VISA_STATUS_REFUSE;
            }else{
                $updateList['reconsideration_status'] = ($is_can_recons) ? FastVisa::VISA_RECONSIDERATION_STATUS_NOT : 0;
            }
            //更新订单状态
            $visaObj->updateVisaStatus($status, ['id'=>$visaId], $updateList);
            //更新审批结果
            //获取最新的visa_result
            $visaResultModel = new FastVisaResult();
            $latestVisaResult = $visaResultModel->getOne(['*'], ['visa_id'=>$visaId], ['id'=>'desc']);
            if (empty($latestVisaResult)) {
                FastException::throwException('获取visa_result失败');
            }
            #直连送黑名单
            $need_push_map = C("@.common.fast_add_black_refuse_tag");
            $is_push_black = 0;
            $is_black = array_intersect($need_push_map,$refuse_tag);
            if ($status == FastVisa::VISA_STATUS_REFUSE && $is_black) {
                try{
                    $params['applyid'] =  $visaInfo['apply_id'];
                    $params['credit_applyid'] =  $visaInfo['credit_apply_id'];
                    $params['black_reason'] =  call_user_func_array(function($is_black){
                        $res = [];
                        foreach ($is_black as $temp){
                            $res[] = FastVisa::$visa_refuse_category[$temp];
                        }
                        return implode(',',$res);
                    },[$is_black]);
                    $params['fullname'] =  $visaInfo['full_name'];
                    $params['mobile'] =  $visaInfo['mobile'];
                    $params['id_card_num'] =  $visaInfo['id_card_num'];
                    $res = CommonApi::addBlack($params);
                    if ($res['code'] == 1) {
                        $is_push_black = 1;
                    }
                }catch (\Exception $e){
                    $is_push_black = 0;
                }
            }
            $visaResultData = [
                'visa_status' => $status,
                'inside_opinion' => $inside_opinion,
                'out_opinion' => $out_opinion,
                'refuse_tag' => implode(',',$refuse_tag),
                'updated_at'=>date('Y-m-d H:i:s'),
                'need_verify' => $need_verify,
                'is_push_black' => $is_push_black
            ];
            if (in_array($visaInfo['reconsideration_status'],[FastVisa::VISA_RECONSIDERATION_STATUS_DOING,FastVisa::VISA_RECONSIDERATION_STATUS_PASS,FastVisa::VISA_RECONSIDERATION_STATUS_REFUSE])) {
                $visaResultData['reconsideration_status'] = $status == FastVisa::VISA_STATUS_AGREE ? FastVisa::VISA_RECONSIDERATION_STATUS_PASS : FastVisa::VISA_RECONSIDERATION_STATUS_REFUSE;
            }else{
                $visaResultData['reconsideration_status'] = $visaInfo['reconsideration_status'];
            }
            $exeResult = (new FastVisaResult())->updateVisaResult($visaResultData, ['id'=>$latestVisaResult['id']]);
            if (!$exeResult) {
                FastException::throwException('更新visa_result失败', 12);
            }
            //更新visa_log 审批时间时间
            $fastVisaLogModel = new FastVisaLog();
            $latestVisaLog = $fastVisaLogModel->getOne(['*'], ['visa_id'=>$visaId], ['id'=>'desc']);
            if (!$latestVisaLog) {
                FastException::throwException('数据异常，查询不到最新的fast_visa_log日志，visa_id:' . $visaId);
            }
            $visaLogUpdateData['visa_time'] = time();
            $visaLogUpdateData['seat_id'] = $seatId;
            $visaLogUpdateData['updated_at'] = date('Y-m-d H:i:s');
            $visaLogUpdateData['visa_status'] = $status;
            if (in_array($visaInfo['reconsideration_status'],
                [FastVisa::VISA_RECONSIDERATION_STATUS_DOING,FastVisa::VISA_RECONSIDERATION_STATUS_PASS,FastVisa::VISA_RECONSIDERATION_STATUS_REFUSE])) {
                $visaLogUpdateData['reconsideration_status'] = $status == FastVisa::VISA_STATUS_AGREE ? FastVisa::VISA_RECONSIDERATION_STATUS_PASS : FastVisa::VISA_RECONSIDERATION_STATUS_REFUSE;
            }
            $exeResult = (new FastVisaLog())->updateVisaLog($visaLogUpdateData, ['id'=>$latestVisaLog['id']]);
            if (!$exeResult) {
                FastException::throwException('数据异常，更新fast_visa_log失败，log_id:' . $latestVisaLog['id']);
            }
            //维护坐席状态（如果勾选了继续处理，则坐席为空闲，否则离开）
            $work_status = ($go_on_hanld_apply) ? SeatManage::SEAT_STATUS_ON : SeatManage::SEAT_WORK_STATUS_LEAVE;
            $exeResult = $seatObj->updateWorkSeatStatus($work_status, ['id'=>$seatId]);
            if (!$exeResult) {
                FastException::throwException('更新状态失败');
            }
            if(config('common.is_use_new_order_apply')) {
                //坐席加入等待队列
                if($work_status !== SeatManage::SEAT_STATUS_ON ){
                    $this->redis->zRem(config('common.auto_apply_seat_key'),$seatId);
                }else{
                    #在线时加入空闲队列并查询挂起排队订单，加入队列
                    $order_arr = $this->redis->zRangeByScore(config('common.auto_apply_order_key'), $seatId, $seatId, array('limit' => array(0, 1)));
                    if(empty($order_arr)){
                        $get_res = $visaObj->getOne(
                            ['id','line_up_time'],
                            ['status'=>FastVisa::VISA_STATUS_HANG_QUEUEING,'seat_id' => $seatId],['line_up_time' => 'asc']);
                        if($get_res){
                            $this->redis->zadd(config('common.auto_apply_order_key'),$seatId,$get_res['id']);
                        }
                    }
                }
                //审核完成后，查询此master_id是否还有挂起排队中订单。
                $fast_visa_rep_obj = new FastVisaRepository();
                $fast_visa_rep_obj->match_master_visa($visaInfo['master_id']);
            }
            //事务结束
            DB::connection('mysql.video_visa')->commit();
            //更新心跳
            $seatObj->updateKeepAliveKey($seatId);
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
        } catch (\Exception $e){
            DB::connection('mysql.video_visa')->rollback();
            return $this->showMsg(self::CODE_FAIL, '操作失败！失败原因：'.$e->getMessage());
        }
    }

    public function getVisaState(Request $request){
        //参数验证
        $type = $request->input('type', '');
        if (!in_array($type, [1,2])) {
            return $this->showMsg(self::CODE_FAIL, 'type错误');
        }
        $visaId = $request->input('visa_id', 0);

        $seatId = session('uinfo.seat_id');

        //更新心跳
        $handingVisaId = (new SeatManage())->getHandingVisaId($seatId);
        /*if ($handingVisaId) {

            $tempVisaIdInfo = (new FastVisa())->getOne(['id'], ['seat_id'=>$seatId,
                'in'=>['status'=>[FastVisa::VISA_STATUS_IN_SEAT,FastVisa::VISA_STATUS_IN_VIDEO,]]
            ]);
            if (empty($tempVisaIdInfo))  return $this->showMsg(self::CODE_SUCCESS, '坐席并不繁忙：'.$seatId);
            $tempVisaId = $tempVisaIdInfo['id'];
        } else {
            $tempVisaId = isset($tempSeatInfo['visa_id']) ? $tempSeatInfo['visa_id'] : '';
        }*/
        $visaId = $handingVisaId ? $handingVisaId : '';
        (new SeatManage())->updateKeepAliveKey($seatId, $visaId);

        if ($type == 1) {

        } else {//2返回挂起个数
            $hangCount = (new FastVisaRepository())->getHangNumBySeatId($seatId);
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS, $hangCount);
        }

        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
    }

    //获取外部信息表格
    public function getOutsideInfo(Request $request,CommonRepository $commonRepository)
    {
        $applyid = $request->input('applyid',0);
        $res = (new CarLoanOrder())->getOne(['fullname','id_card_num','mobile','bank_no'],['applyid'=>$applyid]);

        $order_fee = \App\XinApi\ErpApi::getCarFee($applyid);
        $order_fee = !empty($order_fee[$applyid])? $order_fee[$applyid]:[];
        $webank_apply_data_id = isset($order_fee['webank_apply_data_id'])? (int)$order_fee['webank_apply_data_id']:0;
        $rent_type = isset($order_fee['rent_type'])? (int)$order_fee['rent_type']:0;
        $level = '未知';
        if(!empty($webank_apply_data_id) && !empty($rent_type) ) {
            $car_half_apply = new CarHalfApplyCredit();
            $info = $car_half_apply->getOne(
                ['applyid'],
                [
                    'webank_apply_data_id'=>$webank_apply_data_id,
                    'rent_type' => $rent_type,
                    'carid' => 0
                ],
                ['webank_apply_data_id'=>'desc']);

            if(!empty($info)){
                $level = AptService::get_consumption_power_level($info['applyid']);
                $level_config = config("common.yunhe_consumption_power_level");
                $level = $level_config[$level];
            }
        }
        $res['menu'] = 'xy';
        $res['type'] = 'xyCreditArchives';
        $res['phone_no'] = $res['mobile'];
        $res['id_no'] = $res['id_card_num'];
        $data = $commonRepository->getOutsideInfo($res);
        $data['level'] = $level;
        if(empty($data['result_detail'])){
            return $this->showMsg(self::CODE_FAIL, isset($data['desc'])? $data['desc']:'三方接口没有数据',$data);
        }
        $loan_quota = $loan_count = [];
        if (!empty($data['result_detail']['current_order_count'])) {
            $data['due_time'] = date('Y年m月');
            $data['new_or_old'] = '空';
            $data['due_count'] = $data['result_detail']['current_org_count'];
            $data['current_order_lend_amt'] = $data['result_detail']['current_order_lend_amt'];
        }else{
            $data['due_time'] = $data['result_detail']['totaldebt_detail'][0]['totaldebt_date'];
            $data['new_or_old'] = ($data['result_detail']['totaldebt_detail'][0]['new_or_old'] == "Y")? '是':"否";
            $data['due_count'] = $data['result_detail']['totaldebt_detail'][0]['totaldebt_order_count'];
            $data['current_order_lend_amt'] = $data['result_detail']['totaldebt_detail'][0]['totaldebt_order_lend_amt'];
        }
        foreach ($this->month_time as $val){
            $loan_quota[$val] = 0;
            $loan_count[$val] = 0;
        }
        foreach ($data['result_detail']['totaldebt_detail'] as $item) {
            $month_time = ltrim(explode('-',$item['totaldebt_date'])[1],'0').'月';
            if(!isset($loan_quota[$month_time])){
                continue;
            }
            $loan_quota[$month_time] = (integer)explode('-',$item['totaldebt_order_lend_amt'])[0];
            $loan_count[$month_time] = (integer)$item['totaldebt_order_count'];
        }

        $data['loan_quota'] = $loan_quota;
        $data['loan_count'] = $loan_count;

        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS,$data);
    }

    public function getRelationMember(Request $request) {

        $request = $request->all();
        $uid = $request['uid'];

        $relation_url = config('common.relation_net_url');
        $_s = config('common.relation_s');
        $secret = config('common.relation_secret');
        $params = [
            'uid' => $uid,
            '_s' => $_s,
        ];
        $params['sn'] = Common::create_sn($params, $secret, true);
        $ret = HttpRequest::getJson($relation_url, $params);
        if(empty($ret) || $ret['code'] != 0 || empty($ret['data'])) {
            return [];
        }

        //复制erp展示逻辑
        $return_data_graph = $ret['data']['graph'];
        $return_data_table = $ret['data']['table'];
        $user_fullname = $ret['data']['graph']['1']['user_name'];
        $relation_power = true;

        if(!empty($return_data_graph)) {

            foreach($return_data_graph as $key_1 => $item_1) {
                $user_info[$key_1] = [

                    'user_name' => $item_1['user_name'],
                    'uid' => $item_1['uid'],
                    'id' => $key_1,
                    'is_real' => $item_1['is_real'],
                    'is_blacklist' => $item_1['is_blacklist'],
                    'level' => $item_1['depth'],
                    'id_card_num' =>$item_1['id_card_num'],
                ];
                if(!empty($item_1['relation'])) {
                    foreach($item_1['relation'] as $key_2 => $item_2) {
                        $user_link[] = [$key_1=>$key_2];
                        $user_relation[] = [
                            'id_1' => $key_1,
                            'id_2' => $key_2,
                            'relation' => isset($item_2['relation_name']) ? $item_2['relation_name'] : ''
                        ];
                    }
                }
            }
        }

        return trim(view('fast_visa.relation_member', [
            'user_info'             => $user_info,
            'user_link'             => $user_link,
            'user_relation'         => $user_relation,
            'relation_table'        => $return_data_table,
            'relation_power'        => $relation_power,
            'userid'                => $uid,
            'user_fullname'         => $user_fullname,
        ])->render());
    }

    public function orderTip(Request $request) {
        $request = $request->all();
        $message = $request['message'];
        return trim(view('fast_visa.order_tip', [
            'message' => $message
        ])->render());
    }

    /**
     * 坐席加入队列
     * @param Request $request
     * @return mixed
     */
    public  function addSeatQueue(Request $request)
    {
        $request = $request->all();
        $seat = isset($request['seat_id']) ? (integer)$request['seat_id'] : 0;
        if(!empty($seat)){
            $redis = new RedisCommon();
            if(empty($redis->Zscore(config('common.auto_apply_seat_key'), $seat))){
                $redis->zadd(config('common.auto_apply_seat_key'),time(),$seat);
            }
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS,[]);
        }
        return $this->showMsg(self::CODE_FAIL, self::MSG_FAIL,[]);
    }


    /**
     * 获取资料详情
     * @param Request $request
     */
    public function getExtendList(Request $request) {
        $type = $request->input('type',0);//0资料查看 1复议资料
        $visa_id = $request->input('visa_id',0);//0资料查看 1复议资料
        $err = ['err' => ''];
        $res = [];
        if (!$visa_id) {
           $err['err'] = '参数错误!';
        }else{
            $extendObj = new FastVisaExtend();
            $res = $extendObj->getOne(['id','visa_id','data','remark','is_reconsideration'],['type'=>0,'visa_id' => $visa_id,'is_reconsideration'=>$type? 1 :0],['id' => 'asc']);
            if (!$res) {
                $err['err'] = '无上传资料!';
            }
            $res['data'] = !empty($res['data'])? json_decode($res['data'],true):[];
            $res['count_bank_flow'] = !empty($res['bank_flow'])?  count($res['bank_flow']):0;
            $res['count_proof_of_assets'] = !empty($res['proof_of_assets'])?  count($res['proof_of_assets']):0;
            $res['count_other'] = !empty($res['other'])?  count($res['other']):0;
        }
        return view('common.extend',array_merge($res,$err));
    }
}