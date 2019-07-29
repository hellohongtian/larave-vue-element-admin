<?php
namespace App\Repositories\Visa;

use App\Fast\FastException;
use App\Library\Common;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\NetEase\FastVideoData;
use App\Models\VideoVisa\SeatManage;
use App\Models\Xin\CarHalfApply;
use App\Models\XinCredit\PersonCredit;
use App\Models\XinCredit\PersonCreditResult;
use App\Repositories\BaseRepository;
use App\Repositories\CityRepository;
use App\Repositories\CommonRepository;
use App\Repositories\FastVisaRepository;
use App\Repositories\SeatManageRepository;
use App\Repositories\UserRepository;
use App\User;
use App\Models\XinFinance\CarHalfService;

class VisaRepository extends BaseRepository
{
    public $capital_channel = [
        1 => '微众',
        10 => '新网'
    ];

    //待审核列表
    public function getNeedVisaList($paramList)
    {
        $where = [];
        $where['line_up_time >='] = strtotime(date('Y-m-d'));
        if (isset($paramList['start_time'])) {
            $where['create_time >='] = $paramList['start_time'];
        }
        if (isset($paramList['end_time'])) {
            $where['create_time <'] = $paramList['end_time'];
        }
        if (isset($paramList['id'])) {
            $where['id'] = $paramList['id'];
        }
        $where['in']['status'] = FastVisa::$waitForVisaStatusList;
        if (isset($paramList['status'])) {
            $where['in']['status'] = is_array($paramList['status']) ? $paramList['status'] : [$paramList['status']];
        }
        $keyList = ['apply_id', 'mobile', 'car_id', 'channel', 'business_type',
            'full_name', 'risk_time', 'car_city_id', 'risk_start_name','erp_credit_status','sales_type','id_card_num'];
        foreach ($keyList as $tempKey) {
            if (isset($paramList[$tempKey]))  $where[$tempKey] = $paramList[$tempKey];
        }
        $visa_obj = new FastVisa();
        $visaList = $visa_obj->getList(['*'], $where,[],[],500);
        if($visaList->total() == 0){
            $data = [];
        }else{
            $data = $this->formatVisaListRet($visaList)->toArray()['data'];
        }
        $count = $visa_obj->countBy($where);
        $res = [
            'data' => $data,
            'count' => $count
        ];
        return $res;
    }
    //挂起列表
    public function getHangList($conditions)
    {
        if (UserRepository::isSeat() || UserRepository::isGuest()) {
            $conditions['seat_id'] = session('uinfo.seat_id');
            $conditions['in']['status'] = [
                FastVisa::VISA_STATUS_HANG,
                FastVisa::VISA_STATUS_HANG_QUEUEING,
            ];
        } else {  //管理员
            $conditions['in']['status'] = [
                FastVisa::VISA_STATUS_HANG,
                FastVisa::VISA_STATUS_HANG_QUEUEING,
            ];
            $conditions['seat_id !='] = 0;
        }
        $conditions['line_up_time >='] = strtotime(date('Y-m-d'));
        $visa_obj = new FastVisa();
        $visaList = $visa_obj->getList(['*'], $conditions);
        if($visaList->total() == 0){
            $data = [];
        }else{
            $data = $this->formatVisaListRet($visaList)->toArray()['data'];
        }
        $count = $visa_obj->countBy($conditions);
        $res = [
            'data' => $data,
            'count' => $count
        ];
        return $res;
    }

    //复议列表
    public function getReconsVisaList($paramList)
    {
        $where = [];
        $where['line_up_time >='] = strtotime(date('Y-m-d'));
        if (isset($paramList['start_time'])) {
            $where['create_time >='] = $paramList['start_time'];
        }
        if (isset($paramList['end_time'])) {
            $where['create_time <'] = $paramList['end_time'];
        }
        if (isset($paramList['id'])) {
            $where['id'] = $paramList['id'];
        }
        $where['status'] = FastVisa::VISA_STATUS_REFUSE;
        $where['in']['reconsideration_status'] = [FastVisa::VISA_RECONSIDERATION_STATUS_CAN, FastVisa::VISA_RECONSIDERATION_STATUS_DOING];
        $keyList = ['apply_id', 'mobile', 'car_id', 'channel', 'business_type',
            'full_name', 'risk_time', 'car_city_id', 'risk_start_name','erp_credit_status','sales_type','id_card_num'];
        foreach ($keyList as $tempKey) {
            if (isset($paramList[$tempKey]))  $where[$tempKey] = $paramList[$tempKey];
        }
        $visa_obj = new FastVisa();
        $visaList = $visa_obj->getList(['*'], $where,[],[],500);
        if($visaList->total() == 0){
            $data = [];
        }else{
            $data = $this->formatVisaListRecons($visaList)->toArray()['data'];
        }
        $count = $visa_obj->countBy($where);
        $res = [
            'data' => $data,
            'count' => $count
        ];
        return $res;
    }
    //格式化复议数据
    private function formatVisaListRecons($visaList)
    {
        $cityList = (new CityRepository())->getAllCity(['cityid','cityname']);
        $data = !empty($visaList->toArray()['data'])? $visaList->toArray()['data']:[];
        $receive_list = (new FastVisaRepository())->get_seat_receive_time(array_unique(array_filter(array_column($data,'id'))));
        foreach ($visaList as $tempKey => $tempInfo) {
            if($tempInfo['reconsideration_status'] == FastVisa::VISA_RECONSIDERATION_STATUS_CAN
                || ($tempInfo['seat_id'] == session('uinfo.seat_id') && $tempInfo['reconsideration_status'] == FastVisa::VISA_RECONSIDERATION_STATUS_DOING)){
                $operate = true;
            }else{
                $operate = false;
            }
            $visaList[$tempKey]['line_up_time'] =date('m-d H:i',$tempInfo['line_up_time']);
            $visaList[$tempKey]['erp_credit_status'] = !empty($tempInfo['erp_credit_status'])? FastVisa::$visaErpStatusChineseMap[$tempInfo['erp_credit_status']]:'';
            $visaList[$tempKey]['sales_type'] = !empty($tempInfo['sales_type'])? CarHalfService::purchase_map()[$tempInfo['sales_type']]:'';
            $visaList[$tempKey]['prev_seat_name'] = !empty($tempInfo['prev_seat_id'])? SeatManageRepository::get_cache_user_name($tempInfo['prev_seat_id']):'';
            $visaList[$tempKey]['seat_name'] = !empty($tempInfo['seat_id'])? SeatManageRepository::get_cache_user_name($tempInfo['seat_id']):'';
            $visaList[$tempKey]['created_at'] = !empty($tempInfo['created_at'])? date('m-d H:i',strtotime($tempInfo['created_at'])):'';
            $visaList[$tempKey]['seat_receive_time'] = !empty($receive_list[$tempInfo['id']])? date('m-d H:i',$receive_list[$tempInfo['id']]['seat_receive_time']):'';
            $visaList[$tempKey]['visa_time'] = !empty($tempInfo['visa_time'])? date('m-d H:i',$tempInfo['visa_time']):'';
            $visaList[$tempKey]['car_city_id'] = isset($cityList[$tempInfo['car_city_id']]) ?  $cityList[$tempInfo['car_city_id']]['cityname'] : '' ;
            $visaList[$tempKey]['status_text'] = FastVisa::$visaStatusChineseMap[$tempInfo['status']];
            $visaList[$tempKey]['is_can_visa'] = $operate;
        }
        return $visaList;
    }
//    有结果的列表
    public function getFinishedVisaList($paramList,$pageSize){
        $where = [];
        $where['in'] = ['status' => FastVisa::$visaFinishedStatusList];
        if (isset($paramList['status'])) {
            $where['in'] = ['status' => [$paramList['status']]];
        }
        if (isset($paramList['start_time'])) {
            $where['create_time >='] = $paramList['start_time'];
        }
        if (isset($paramList['end_time'])) {
            $where['create_time <'] = $paramList['end_time'];
        }

        $keyList = ['apply_id', 'mobile', 'car_id', 'channel', 'business_type',
            'full_name', 'seat_id', 'risk_time', 'car_city_id', 'risk_start_name'];
        foreach ($keyList as $tempKey) {
            if (isset($paramList[$tempKey]))  $where[$tempKey] = $paramList[$tempKey];
        }

        //获取有结果的fast_visa
        $fastVisaModel = new FastVisa();
        $query = $fastVisaModel->selectRaw('fast_visa.*');
        $list = $fastVisaModel->createWhere($query, $where, ['fast_visa.id'=>'desc'])->paginate($pageSize);
        $list->setPath('');

        //获取每个fast_visa_id最新的一条fast_visa_log
        $visaIds = [];
        foreach($list as $each) {
            $visaIds[] = $each['id'];
        }
        $fastVisaLogList = [];
        $logFields = ['id', 'visa_id', 'queuing_time', 'seat_receive_time', 'call_video_time', 'end_video_time', 'visa_time'];
        $fastVisaLogListTmp = (new FastVisaLog())->getAll($logFields, ['in'=>['visa_id'=>$visaIds]], [], [], true);
        foreach($fastVisaLogListTmp as $each){
            if (!isset($fastVisaLogList[$each['visa_id']]) || ($fastVisaLogList[$each['visa_id']]['id'] < $each['id'])) {
                $fastVisaLogList[$each['visa_id']] = $each;
            }
        }
        unset($fastVisaLogListTmp);

        //fast_visa 和 fast_visa_log建立连接
        foreach ($list as &$each) {
            if (isset($fastVisaLogList[$each['id']])) {
                $each->queuing_time = $fastVisaLogList[$each['id']]['queuing_time'];
                $each->seat_receive_time = $fastVisaLogList[$each['id']]['seat_receive_time'];
                $each->call_video_time = $fastVisaLogList[$each['id']]['call_video_time'];
                $each->end_video_time = $fastVisaLogList[$each['id']]['end_video_time'];
                $each->visa_time = $fastVisaLogList[$each['id']]['visa_time'];
            } else {
                $each->queuing_time = 0;
                $each->seat_receive_time = 0;
                $each->call_video_time = 0;
                $each->end_video_time = 0;
                $each->visa_time = 0;
            }
        }
        unset($fastVisaLogList);

        //获取视频相关信息
        $videoListTmp = (new FastVideoData())->getAll(['id', 'url', 'visa_id'], ['in' => ['visa_id' => $visaIds]]);
        $videoList = [];
        foreach ($videoListTmp as $each) {
            if (!isset($videoList[$each['visa_id']]) || $each['id'] > $videoList[$each['visa_id']]['id']) {
                $videoList[$each['visa_id']] = $each;
            }
        }
        foreach ($list as $k => &$v) {
            $v['status_cn'] = isset(FastVisa::$visaStatusChineseMap[$v['status']]) ? FastVisa::$visaStatusChineseMap[$v['status']] : '';
            $v['channel_cn'] = isset($this->capital_channel[$v['channel']]) ? $this->capital_channel[$v['channel']] : '未知';
            //获取对应的视频地址
            $v['video_url'] = isset($videoList[$v['id']]) ? $videoList[$v['id']]['url'] : '';
        }
        unset($videoList);

        return $this->formatVisaListRet($list);
    }

    public function getVisaInfo($visaId)
    {
        $visaInfo = (new FastVisa())->getOne(['*'], ['id' => $visaId]);
        $visaResult = (new FastVisaResult())->getOne(['*'], ['id' => $visaId]);
        $visaInfo['inside_opinion'] = isset($visaResult['inside_opinion']) ? $visaResult['inside_opinion']:'';
        $visaInfo['out_opinion'] = isset($visaResult['out_opinion']) ? $visaResult['out_opinion']:'';
        $applyId = $visaInfo['apply_id'];
        //获取数据
        $commonRepos = new CommonRepository();
        $data = $commonRepos->getVisaDetail($visaInfo);

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
            'decision_info' => $this->decision_info,
            'remark_info' => $this->remark_info,
        ];

    }

    private function formatVisaListRet($visaList)
    {
        $cityList = (new CityRepository())->getAllCity(['cityid','cityname']);
        $data = !empty($visaList->toArray()['data'])? $visaList->toArray()['data']:[];
        $receive_list = (new FastVisaRepository())->get_seat_receive_time(array_unique(array_filter(array_column($data,'id'))));
        foreach ($visaList as $tempKey => $tempInfo) {
            if(session('uinfo.flag') == 3 && ($tempInfo['status']) == 9 || (!empty($tempInfo['seat_id']) && $tempInfo['seat_id'] == session('uinfo.seat_id') && in_array(intval($tempInfo['status']),[3,4,11]))){
                $operate = true;
            }else{
                $operate = false;
            }
            $visaList[$tempKey]['line_up_time'] =!empty($tempInfo['line_up_time'])? date('m-d H:i',$tempInfo['line_up_time']):"" ;
            $visaList[$tempKey]['erp_credit_status'] = !empty($tempInfo['erp_credit_status'])? FastVisa::$visaErpStatusChineseMap[$tempInfo['erp_credit_status']]:'';
            $visaList[$tempKey]['sales_type'] = !empty($tempInfo['sales_type'])? CarHalfService::purchase_map()[$tempInfo['sales_type']]:'';
            $visaList[$tempKey]['prev_seat_name'] = !empty($tempInfo['prev_seat_id'])? SeatManageRepository::get_cache_user_name($tempInfo['prev_seat_id']):'';
            $visaList[$tempKey]['seat_name'] = !empty($tempInfo['seat_id'])? SeatManageRepository::get_cache_user_name($tempInfo['seat_id']):'';
            $visaList[$tempKey]['created_at'] = !empty($tempInfo['created_at'])? date('m-d H:i',strtotime($tempInfo['created_at'])):'';
            $visaList[$tempKey]['seat_receive_time'] = !empty($receive_list[$tempInfo['id']])? date('m-d H:i',$receive_list[$tempInfo['id']]['seat_receive_time']):'';
            $visaList[$tempKey]['visa_time'] = !empty($tempInfo['visa_time'])? date('m-d H:i',$tempInfo['visa_time']):'';
            $visaList[$tempKey]['car_city_id'] = isset($cityList[$tempInfo['car_city_id']]) ?  $cityList[$tempInfo['car_city_id']]['cityname'] : '' ;
            $visaList[$tempKey]['status_text'] = FastVisa::$visaStatusChineseMap[$tempInfo['status']];
            $visaList[$tempKey]['is_can_visa'] = $operate;
        }
        return $visaList;
    }

    public function getHangListBySeatId($seatId){
        $where = ['seat_id' => $seatId,'status' => FastVisa::VISA_STATUS_HANG];
        $hangList = (new FastVisa())->getList(['*'], $where);
        return $this->formatVisaListRet($hangList);
    }
}