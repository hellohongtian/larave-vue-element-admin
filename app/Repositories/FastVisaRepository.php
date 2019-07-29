<?php
/**
 * 面签
 * User: wood
 * Date: 2017/10/16
 */
namespace App\Repositories;

use App\Library\RedisCommon;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\SeatManage;
use Illuminate\Support\Facades\DB;
use App\Library\Common;
use App\Models\VideoVisa\ErrorCodeLog;

class FastVisaRepository
{
    public $seat_model = null;
    public $seat_model_log = null;
    protected $visa_model = null;
    //异常数据邮件
    protected $api_error_email;
    //订单队列key
    protected $order_key;
    //visa锁
    const VISA_LOCK_KEY = 'fast_visa_lock_';

    function __construct()
    {
//        $this->api_error_email =  config('mail.developer');
//        $this->seat_model = new SeatManage();
//        $this->seat_model_log = new SeatManagerLog();
        $this->order_key = config('common.auto_apply_order_key');
        $this->visa_model = new FastVisa();
    }

    public $symbol = [
        'status' => '=',
        'carid' => '=',
        'channel' => '=',
        'applyid' => '=',
        'fullname' => 'like',
        'car_name' => 'like',
        'business_type' => '=',
        'status_all' => 'in',
        'created_at' => '>=',
        'end_time' => '<=',
        'seat_id' => '=',
        'visa_time' => '>=',
    ];


    /**
     * 获取全部坐席 或者指定人  在指定H间段的相关数据总量
     * @param  array  $where [description]
     * @return [type]        [description]
     */
    public function getVisaHandleTimeStatics($where = []) {

        $result = [];

        $startTimeUnix = $where['start_time'];
        $endTimeUnix = $where['end_time'];

        $fastVisaLogModel = new FastVisaLog();

        //获取当天的有visa_time 的visa_log
        $query = $fastVisaLogModel->select('seat_receive_time', 'queuing_time', 'visa_time', 'call_video_time', 'end_video_time');
        if (isset($where['seat_id']) && $where['seat_id'] > 0) {
            $query->where('seat_id', '=', $where['seat_id']);
        }
        $query->where('visa_status', '>', 0)
            ->where('seat_receive_time' , '>',0)
            ->where('queuing_time' , '>',0)
            ->where('visa_time', '>=', $startTimeUnix)
            ->where('visa_time', '<', $endTimeUnix);

        $visaArr = $query->get()->toArray();

        //获取 5通过  6拒绝 7跳过总数
        $visaCount = count($visaArr);
        $result['audit_pass_reject_jump'] = $visaCount;

        $allQueueToReceiveTime = 0;
        $allReceiveToVisaHandleTime = 0;
        $allVideoTime = 0;
        foreach($visaArr as $each) {
            $allQueueToReceiveTime += $each['seat_receive_time'] - $each['queuing_time'];
            $allReceiveToVisaHandleTime += $each['visa_time'] - $each['seat_receive_time'];
            $allVideoTime += $each['end_video_time'] - $each['call_video_time'];
        }

        //用户排队平均时长
        $result['avg_time_long'] = $visaCount==0 ? 0 : intval($allQueueToReceiveTime / $visaCount);
        if ($result['avg_time_long'] != 0) {
            $result['avg_time_long'] = gmdate("H:i:s", $result['avg_time_long']);
        }

        //面签平均处理时长
        $result['receive_to_visa_avg_time'] = $visaCount==0 ? 0 : intval($allReceiveToVisaHandleTime / $visaCount);
        if ($result['receive_to_visa_avg_time'] != 0) {
            $result['receive_to_visa_avg_time'] = gmdate("H:i:s", $result['receive_to_visa_avg_time']);
        }

        // 平均语音通话时长
        $result['video_avg_time'] = $visaCount==0 ? 0 : intval($allVideoTime / $visaCount);
        if ($result['video_avg_time'] != 0) {
            $result['video_avg_time'] = gmdate("H:i:s", $result['video_avg_time']);
        }
        return $result;
    }

    /**
     * 从领取到面签完成时间
     * @param int $startTime
     * @param int $endTime
     * @param int $seat_id
     * @return int
     */
    public function getTimeReceiveToVisa($startTime=0,$endTime=0,$seat_id=0){
        $logModel = new FastVisaLog();
        $query = $logModel->selectRaw("count(id) as countnum,sum(visa_time-seat_receive_time) as alltime");
        if($startTime > 0){
            $query->where('visa_time','>=', "$startTime");
        }
        if($endTime > 0){
            $query->where('visa_time','<=', "$endTime");
        }

        $query->whereIn('visa_status', FastVisa::$visaFinishedStatusList);

        if($seat_id > 0){
            $query->where('seat_id','=', $seat_id);
        }

        $arr = $query->get()->toArray();

        if ($arr[0]['alltime'] >0 && !empty($arr[0]['countnum'])) {
            $avg_time = intval($arr[0]['alltime'] / $arr[0]['countnum']);
        } else {
            $avg_time = 0;
        }

//        print_r(\Illuminate\Support\Facades\DB::getQueryLog());die;

        return $avg_time;
    }

    //座席审核排行
    public function getVisaTopUser($visa_time,$limit = 5){
        $fastVisaModel = new FastVisa();
        $res = $fastVisaModel->selectRaw('count(fast_visa.id) as count,'.'fast_visa'.'.seat_id')
            ->where('visa_time','>=', $visa_time)
            ->whereIn('status', FastVisa::$visaFinishedStatusList)
            ->orderBy('count', 'desc')
            ->groupBy('fast_visa.seat_id')
            ->limit($limit)
            ->get()->toArray();

        if ($res) {
            //通过seatID获取相关坐席信息
            $mangeModel = new SeatManage();
            foreach ($res as $k => $v) {
                $r = $mangeModel->select("fullname")->where(['id' => $v['seat_id']])->first();
                if ($r) {
                    $r = $r->toArray();
                    $res[$k]['fullname'] = $r['fullname'];
                } else {
                    $res[$k]['fullname'] = '';
                }
            }
        }
        return $res;

    }
    //获取top 5的
    /**
     * [getTopNum description]
     * @param  array   $where [description]
     * @param  integer $limit [description]
     * @return [type]         [description]
     */
    public function getTopNum($where = [], $limit = 5) {
        $fastVisaModel = new FastVisa();
        $res = $fastVisaModel->selectRaw('count(fast_visa.id) as count,'.'fast_visa'.'.seat_id')
            ->rightJoin('fast_visa_log','fast_visa.id','=','fast_visa_log.visa_id')
            ->where(function ($query) use ($where) {
                foreach ($where as $key => $value) {
                    if (!empty($value)) {
                        if ($this->symbol[$key] == 'like') {
                            $query->where('fast_visa'.'.'.$key, $this->symbol[$key], '%' . $value . '%');
                        } elseif ($key == 'create_time') {
                            $query->where('fast_visa_log.queuing_time', '>=', $value);
                        } elseif ($key == 'end_time') {
                            $query->where("fast_visa_log.create_time", '<=', $value);
                        } elseif ($key == 'visa_time') {
                            $query->where("fast_visa_log.visa_time", '>=', $value);
                        }elseif ($key == 'status') {
                            $query->whereIn('fast_visa'.'.'.$key, $value);
                        } else {
                            $query->where('fast_visa'.'.'.$key, $this->symbol[$key], $value);
                        }
                    }
                }
            })->orderBy('count', 'desc')->groupBy('fast_visa'.'.'.'seat_id')->limit($limit)
            ->get()->toArray();
        if ($res) {
            //通过seatID获取相关坐席信息
            $mangeModel = new SeatManage();
            foreach ($res as $k => $v) {
                $r = $mangeModel->select("fullname")->where(['id' => $v['seat_id']])->first();
                if ($r) {
                    $r = $r->toArray();
                    $res[$k]['fullname'] = $r['fullname'];
                } else {
                    $res[$k]['fullname'] = '';
                }
            }
        }
        return $res;
    }

    /**
     * 成功锁定返回true，改锁已被占用返回false
     * @param $visaId
     * @return bool
     */
    public static function lockVisa($visaId)
    {
        if (self::isVisaLocked($visaId)) {
            return false;
        }

        (new RedisCommon())->setex(self::getVisaLockKey($visaId), $visaId, 5);

        return true;
    }

    /**
     * 解锁visa
     * @param $visaId
     */
    public static function unlockVisa($visaId)
    {
        (new RedisCommon())->delete(self::getVisaLockKey($visaId));
    }

    /**
     * 是否visa已被锁
     * @param $visaId
     * @return bool
     */
    public static function isVisaLocked($visaId)
    {
        $value = (new RedisCommon())->get(self::getVisaLockKey($visaId));

        return $value===false ? false : true;
    }

    /**
     * 返回visa锁的缓存key
     * @param $visaId
     * @return string
     */
    public static function getVisaLockKey($visaId)
    {
        return self::VISA_LOCK_KEY . $visaId;
    }

    /**
     * 获取各个面签结果的数量
     * @param array $status_arr
     * @param $start_date
     * @param $end_date
     * @param int $seatId
     * @return array
     */
    public function getVisaCount($status_arr=[], $start_date, $end_date, $seatId=0)
    {
        $statusCount = [
            'audit_pass' => 0,
            'audit_reject' => 0,
            'visa_jump' => 0,
            'queue_again' => 0,
            'hangdata' => 0
        ];
        $end_date = date('Y-m-d',strtotime($end_date.'+1 day'));
        $visaLogModel = new FastVisaResult();
        $query = $visaLogModel->selectRaw("count(id) as count, visa_status as status");
        if ($seatId > 0) {
            $query->where('seat_id', '=', $seatId);
        }
        $query->where('created_at', '>=', $start_date);
        $query->where('created_at', '<', $end_date);
        $query->whereIn('visa_status', $status_arr);

        $res = $query->groupBy('visa_status')->get()->toArray();

        if (!empty($res)) {
            foreach ($res as $info) {
                if ($info['status'] == FastVisa::VISA_STATUS_AGREE) { //通过
                    $statusCount['audit_pass'] = $info['count'] > 0 ? $info['count'] : 0;
                }
                if ($info['status'] == FastVisa::VISA_STATUS_REFUSE) { //拒绝
                    $statusCount['audit_reject'] = $info['count'] > 0 ? $info['count'] : 0;
                }
                if ($info['status'] == FastVisa::VISA_STATUS_SKIP) { //跳过
                    $statusCount['visa_jump'] = $info['count'] > 0 ? $info['count'] : 0;
                }
                if ($info['status'] == FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT) { //重新排队
                    $statusCount['queue_again'] = $info['count'] > 0 ? $info['count'] : 0;
                }
                if ($info['status'] == FastVisa::VISA_STATUS_HANG) { //挂起
                    $statusCount['hangdata'] = $info['count'] > 0 ? $info['count'] : 0;
                }
            }
        }

        $statusCount['audit_pass_reject_jump'] = $statusCount['audit_pass'] + $statusCount['audit_reject'] + $statusCount['visa_jump'];

        return $statusCount;
    }

    /**
     * 查看坐席挂起的数量
     * @return mixed
     */
    public function getHangNumBySeatId($seatId)
    {
        $paramList['seat_id'] = $seatId;
        $paramList['in']['status'] = [
            FastVisa::VISA_STATUS_NOT_IN_QUEUE,
            FastVisa::VISA_STATUS_IN_QUEUEING,
            FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT,
            FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK,
            FastVisa::VISA_STATUS_HANG,
        ];

        return (new FastVisa())->countBy($paramList);
    }

    /**
    *查找所有挂起排队中的订单
    */
    public function getHangQueueingVisaList() {

        $params['status'] = FastVisa::VISA_STATUS_HANG_QUEUEING;
        $visaModel = new FastVisa();
        $visaList = $visaModel->getAll(['seat_id'], $params);
        return $visaList;
    }


    /**
     * 为坐席分配订单
     * @param $visaInfo
     * @param $allotSeatId
     * @return bool
     */
    public function normalVisaAllocSeat($visaInfo, $allotSeatId) {
        $visaModel = new FastVisa();
        $seatObj = new SeatManage();
        if(empty($visaInfo) || empty($allotSeatId)){
            return false;
        }
        if(FastVisa::lockVisa($visaInfo['id'])){
            return false;
        }
        try{
            DB::connection('mysql.video_visa')->beginTransaction();
            $seatWhere = ['id' => $allotSeatId, 'work_status' => SeatManage::SEAT_WORK_STATUS_FREE];
            $seatRet = $seatObj->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_BUSY, $seatWhere);
            if (!$seatRet) {
                throw new \Exception('坐席状态更改为2繁忙失败,where条件->'.json_encode($seatWhere));
            }
            $time = time();
            $visaWhere = ['id' => $visaInfo['id']];
            $extraUpdateData['seat_id'] = $allotSeatId;
            $visaRet = $visaModel->updateVisaStatus(FastVisa::VISA_STATUS_IN_SEAT, $visaWhere, $extraUpdateData);
            if (!$visaRet) {
                throw new \Exception('订单状态更改为3失败,where条件->'.json_encode($visaWhere).'--set附加条件'.json_encode($extraUpdateData));
            }
            //插入visa_log
            $visaLog = [
                'visa_id' => $visaInfo['id'],
                'master_id' => $visaInfo['master_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'queuing_time' => $visaInfo['line_up_time'],
                'seat_receive_time' => $time,
                'seat_id' =>$allotSeatId,
                'match_order_type' => FastVisaLog::MATCH_ORDER_TYPE_AUTO,
            ];
            $exeResult = (new FastVisaLog())->insertVisaLog($visaLog);
            if (!$exeResult) {
                throw new \Exception('FastVisaLog新增失败,数据->'.json_encode($visaLog));
            }
            DB::connection('mysql.video_visa')->commit();
            //更新心跳
            $seatObj->updateKeepAliveKey($allotSeatId,$visaInfo['id']);
            FastVisa::unLockVisa($visaInfo['id']);
            return true;
        }catch (\Exception $e){
            DB::connection('mysql.video_visa')->rollback();
            FastVisa::unLockVisa($visaInfo['id']);
            (new ErrorCodeLog())->runLog(config('errorLogCode.autoApplyCron'), $e->getMessage());
            return false;
        }

    }


    /**
     *查看坐席挂起排队的数量
     */
    public function getHangQueueNumBySeatId($seatId) {

        $paramList['seat_id'] = $seatId;
        $paramList['in']['status'] = [
            FastVisa::VISA_STATUS_HANG_QUEUEING,
        ];

        return (new FastVisa())->countBy($paramList);
    }


    /**
     * 为坐席分配挂起订单
     * @param $visaInfo
     * @param $seatId
     * @return bool
     */
    public function hangVisaAllocSeat($visaInfo, $seatId) {

        $seatObj = new SeatManage();
        $visaModel = new FastVisa();
        if(FastVisa::lockVisa($visaInfo['id'])){
            return false;
        }
        try{
            DB::connection('mysql.video_visa')->beginTransaction();
            $seatWhere = ['id' => $visaInfo['seat_id'], 'work_status' => SeatManage::SEAT_WORK_STATUS_FREE];
            $seatRet = $seatObj->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_BUSY, $seatWhere);
            if (!$seatRet) {
                throw new \Exception('seatRet更改失败');
            }
            $time = time();
            $visaWhere = ['id' => $visaInfo['id']];
            $extraUpdateData['seat_id'] = $seatId;
            $extraUpdateData['line_up_time'] = $time;
            $visaRet = $visaModel->updateVisaStatus(FastVisa::VISA_STATUS_IN_SEAT, $visaWhere, $extraUpdateData);
            if (!$visaRet) {
                throw new \Exception('visaRet更改失败');
            }
            //插入visa_log
            $visaLog = [
                'visa_id' => $visaInfo['id'],
                'master_id' => $visaInfo['master_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'queuing_time' => $time,
                'seat_receive_time' => $time,
            ];
            $exeResult = (new FastVisaLog())->insertVisaLog($visaLog);
            if (!$exeResult) {
                throw new \Exception('exeResult更改失败');
            }
            DB::connection('mysql.video_visa')->commit();
            FastVisa::unLockVisa($visaInfo['id']);
            return true;
        }catch (\Exception $e){
            DB::connection('mysql.video_visa')->rollback();
            FastVisa::unLockVisa($visaInfo['id']);
            return false;
        }

    }

    /**
     * 坐席切换空闲状态，主动寻找订单，禁用
     * @param $seatId
     */
    public function freeSeatMatchVisaOld($seatId) {
        $visaModel = new FastVisa();

        while(true) {
            //先该坐席找挂起排队订单
            $visaInfo = $visaModel->getOne(['id', 'status', 'master_id', 'seat_id'], ['seat_id'=>$seatId, 'status'=>FastVisa::VISA_STATUS_HANG_QUEUEING], ['line_up_time'=>'asc']);
            if(!empty($visaInfo)) {
                $result = $this->normalVisaAllocSeat($visaInfo, $seatId);
                if($result) {
                    Common::notifyFontNewVisa($seatId, FastVisa::NOTIFY_SEAT_HANG_VISA_BACK);
                    break;
                }
            }

            //坐席获取排队中订单（如果并发量变大，可以通过hash或随机数的方式取排队单）
            $visaInfo = $visaModel->getOne(['id', 'status', 'master_id', 'seat_id'], ['status'=>FastVisa::VISA_STATUS_IN_QUEUEING], ['line_up_time'=>'asc']);
            if(empty($visaInfo)) {
                break;
            }

            $result = $this->normalVisaAllocSeat($visaInfo, $seatId);
            if($result) {
                Common::notifyFontNewVisa($seatId, FastVisa::NOTIFY_SEAT_NEW_VISA);
                break;
            }

            //查找自己的状态，如果不是空闲了(说明被新来的其他订单获取走了)，则退出
            $seatObj = new SeatManage();
            $seatInfo = $seatObj->getAll(['id'], ['id'=>$seatId, 'status'=>SeatManage::SEAT_STATUS_ON, 'work_status' => SeatManage::SEAT_WORK_STATUS_FREE]);
            if(empty($seatInfo)) {
                break;
            }
        }
    }

    /**
     * 坐席切换空闲状态，主动寻找订单
     * @param $seatId
     * @return bool
     */
    public function freeSeatMatchVisa($seatId) {
        $visaModel = new FastVisa();
        //先该坐席找挂起排队订单
        $visaInfo = $visaModel->getOne(['id', 'status', 'master_id', 'seat_id'], ['seat_id'=>$seatId, 'status'=>FastVisa::VISA_STATUS_HANG_QUEUEING], ['line_up_time'=>'asc']);
        if(!empty($visaInfo)) {
            $result = $this->normalVisaAllocSeat($visaInfo, $seatId);
            if($result) {
                Common::notifyFontNewVisa($seatId, FastVisa::NOTIFY_SEAT_HANG_VISA_BACK);
                return true;
            }
        }else{
            //坐席获取排队中订单（如果并发量变大，可以通过hash或随机数的方式取排队单）
            $visaInfo = $visaModel->getOne(['id', 'status', 'master_id', 'seat_id'], ['status'=>FastVisa::VISA_STATUS_IN_QUEUEING], ['line_up_time'=>'asc']);
            if(empty($visaInfo)) {
                return false;
            }
            $result = $this->normalVisaAllocSeat($visaInfo, $seatId);
            if($result) {
                Common::notifyFontNewVisa($seatId, FastVisa::NOTIFY_SEAT_NEW_VISA);
                return true;
            }
        }
        return false;

    }

    /**
     * 维护master的队列机制
     * 如果销售masterid已经存在3处理中(已领取未视频) 4视频中 订单，则不允许排队，订单排队，发起排队，退出排队，心跳监控，审批完成调用 优先级挂起排队>可领取>排队中
     * @param $master_id
     * @return mixed
     */
    public function match_master_visa($master_id){

        try{
            $visaObj = new FastVisa();
            $redis_obj = new RedisCommon();
            //获取3处理中(已领取未视频) 4视频中
            $inHandleVisaList = $visaObj->getAll(['id','status','master_id'],
                ['in' =>['status'=>[
                    FastVisa::VISA_STATUS_IN_SEAT,
                    FastVisa::VISA_STATUS_IN_VIDEO,
                ]],'master_id'=>$master_id,'updated_at >=' => date('Y-m-d')]);
            if(!empty($inHandleVisaList)){
                throw new \Exception('存在处理中订单->master_id->'.$master_id);
            }
            //查询该master挂起排队订单，如有，则删除队列中之前得可领取得订单，加入挂起订单
            $hang_res = $visaObj->getOne(
                ['id','master_id','status','line_up_time','seat_id'],
                ['status'=>FastVisa::VISA_STATUS_HANG_QUEUEING,'master_id' => $master_id],['line_up_time' => 'asc']);
            //查询最早一条可领取订单
            $get_res = $visaObj->getOne(
                ['id','line_up_time','sales_type'],
                ['status'=>FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK,'master_id' => $master_id],['line_up_time' => 'asc']);
            //存在挂起排队订单
            if(!empty($hang_res)){
                #队列中已存在挂起排队订单
                if($redis_obj->zRangeByScore($this->order_key, $hang_res['seat_id'], $hang_res['seat_id'], array('limit' => array(0, 1)))){
                    return false;
                }
                #查询该坐席最新挂起订单
                $seat_hang = $visaObj->getOne(
                    ['id','master_id','status','line_up_time','seat_id'],
                    ['status'=>FastVisa::VISA_STATUS_HANG_QUEUEING,'seat_id' => $hang_res['seat_id']],['line_up_time' => 'asc']);
                if(!empty($seat_hang)){
                    #删除一般订单队列
                    if($seat_hang['id'] == $hang_res['id'] && !empty($get_res)){
                        $redis_obj->zRem($this->order_key,$get_res['id']);
                    }
                    #加入挂起排队队列
                    $redis_obj->zadd($this->order_key,$seat_hang['seat_id'],$seat_hang['id']);
                }
                return true;
            }
            $sales_key = C('@.common.apply_sale_type_zset_key.'.$get_res['sales_type']);
            //不存在挂起排队订单
            if(!empty($get_res) && $redis_obj->zScore($this->order_key,$get_res['id'])){
                throw new \Exception('已存在队列中订单->master_id->'.$master_id.'订单id->'.$get_res['id']);
            }elseif (!empty($get_res)){
                $redis_obj->zadd($this->order_key,$get_res['line_up_time'],$get_res['id']);
                if ($sales_key) {
                    $redis_obj->zadd($sales_key,$get_res['line_up_time'],$get_res['id']);
                }
            }else{
                //查询master_id对应最早的正常排队订单
                $master_res = $visaObj->getOne(
                    ['id','master_id','status','line_up_time','sales_type'],
                    ['status'=>FastVisa::VISA_STATUS_IN_QUEUEING,'master_id' => $master_id],['line_up_time' => 'asc']);
                $sales_key = C('@.common.apply_sale_type_zset_key.'.$master_res['sales_type']);
                if(!empty($master_res)){
                    $visaWhere = ['id' => $master_res['id'], 'status' => FastVisa::VISA_STATUS_IN_QUEUEING];
                    $visaObj->updateVisaStatus(FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK, $visaWhere);
                    $redis_obj->zadd($this->order_key,$master_res['line_up_time'],$master_res['id']);
                    if ($sales_key) {
                        $redis_obj->zadd($sales_key,$master_res['line_up_time'],$master_res['id']);
                    }
                }
            }
            return true;
        }catch (\Exception $e){
            return $e->getMessage();
        }

    }

    /**
     * 获取领取订单时间
     * @param array $seat_arr
     */
    public function get_seat_receive_time($visa_arr)
    {
        $visa_str = implode(',',$visa_arr);
        DB::setFetchMode(\PDO::FETCH_ASSOC);
        $visa_res = DB::select(" select * from (SELECT visa_id,seat_receive_time FROM fast_visa_log WHERE visa_id in ({$visa_str}) order by id desc) as a group by visa_id");
        return array_column($visa_res,null,'visa_id');
    }

    /**
     * 获取统计报表数据
     * @param $range
     * @param $start
     * @param $end
     */
    public function get_all_report_list($range,$start,$end,$page,$limit)
    {
        if(empty($range) || empty($start) || empty($end)){
            return false;
        }
        $visa_res = [];
        $visa_sql = '';
        DB::setFetchMode(\PDO::FETCH_ASSOC);
        switch ($range) {
            case 'day':
                $visa_res = DB::table('fast_visa_report')
                    ->select(DB::raw("date,SUM(pass_refuse_count) as pass_refuse_count,SUM(refuse_count) as refuse_count,SUM(jump_count) as jump_count,AVG(avg_queue_time) as avg_queue_time,
                    AVG(avg_deal_time) as avg_deal_time,AVG(total_online_time) as avg_online_time,AVG(total_busy_time) as avg_busy_time,AVG(total_leave_time) as avg_leave_time"))
                    ->where('date', '>=',$start)
                    ->where('date', '<=', $end)
                    ->groupBy('date')
                    ->skip($page*$limit)
                    ->take($limit)
                    ->get()
                    ->toArray();
                $visa_sql = "select * from fast_visa_report where date >= '{$start}' and date < '{$end}'  group by date";
                break;
            case 'week':
                $visa_res = DB::table('fast_visa_report')
                    ->select(DB::raw("FROM_UNIXTIME(UNIX_TIMESTAMP(date),'%Y-%u') time1,date,SUM(pass_refuse_count)  pass_refuse_count,SUM(refuse_count) refuse_count,SUM(jump_count) jump_count,
                    AVG(avg_queue_time) avg_queue_time,AVG(avg_deal_time) avg_deal_time,AVG(total_online_time) avg_online_time,AVG(total_busy_time) avg_busy_time,AVG(total_leave_time) avg_leave_time"))
                    ->where('date', '>=',$start)
                    ->where('date', '<=', $end)
                    ->groupBy('time1')
                    ->skip($page*$limit)
                    ->take($limit)
                    ->get()
                    ->toArray();
                $visa_sql = "select FROM_UNIXTIME(UNIX_TIMESTAMP(date),'%Y-%u') as  time1 from fast_visa_report where date >= '{$start}' and date < '{$end}'  group by time1";
                break;
            case 'month':
                $visa_res = DB::table('fast_visa_report')
                    ->select(DB::raw("FROM_UNIXTIME(UNIX_TIMESTAMP(date),'%Y-%m') time,SUM(pass_refuse_count) pass_refuse_count,SUM(refuse_count) refuse_count,SUM(jump_count) jump_count,
                    AVG(avg_queue_time) avg_queue_time,AVG(avg_deal_time) avg_deal_time,AVG(total_online_time) avg_online_time,AVG(total_busy_time) avg_busy_time,AVG(total_leave_time) avg_leave_time "))
                    ->where('date', '>=', $start.'-01')
                    ->where('date', '<', date('Y-m-d',strtotime($end.'+1 month')))
                    ->groupBy('time')
                    ->orderby('time','asc')
                    ->skip($page*$limit)
                    ->take($limit)
                    ->get()
                    ->toArray();
                $start_str = "'{$start}-01'";
                $end_str = "'".date('Y-m-d',strtotime($end.'-01 +1 day'))."'";
                $visa_sql = "select FROM_UNIXTIME(UNIX_TIMESTAMP(date),'%Y-%m') as  time from fast_visa_report where date >= {$start_str} and date < {$end_str}  group by time";
                break;
        }
        $visa_res = $this->get_report_total($this->format_report_all($visa_res,$range));
        $visa_count = count(DB::select($visa_sql));
        return ['data' => $visa_res,'count' => $visa_count];
    }

    /**
     * 获取统计报表数据
     * @param $range
     * @param $start
     * @param $end
     * @param $page
     * @param $limit
     * @param $seat_id
     * @return mixed
     */
    public function get_seat_report_list($range,$start,$end,$page,$limit,$seat_id)
    {
        if(empty($range) || empty($start) || empty($end)){
            return false;
        }
        $and_where = $visa_sql ='';
        $visa_res = [];
        if($seat_id){
            $and_where = " and seat_id = $seat_id ";
        }
        DB::setFetchMode(\PDO::FETCH_ASSOC);
        switch ($range) {
            case 'day':
                $visa_res_l = DB::table('fast_visa_report')->select(DB::raw("*"))->where('date', '>=', $start)->where('date', '<=', $end);
                if($seat_id){
                    $visa_res_l = $visa_res_l->where('seat_id', '=', $seat_id);
                }
                $visa_res = $visa_res_l->orderby('date','asc')->skip($page*$limit)->take($limit)->get()->toArray();
                $visa_sql = "select * from fast_visa_report where date >= '{$start}' and date <= '{$end}' $and_where";
                break;
            case 'week':
                $sql = "select FROM_UNIXTIME(UNIX_TIMESTAMP(date),'%Y-%u') time,seat_name,date,count(*) as count,SUM(pass_refuse_count) as pass_refuse_count,
SUM(refuse_count) refuse_count,SUM(jump_count) jump_count,AVG(avg_deal_time) avg_deal_time,SUM(total_online_time) total_online_time,SUM(total_busy_time) total_busy_time,SUM(total_leave_time) total_leave_time
from fast_visa_report
where date >= '$start' and date <= '$end' {$and_where}
group by time,seat_id";
                $page_z = $page * $limit;
                $visa_res = DB::select($sql." limit {$page_z},{$limit}");
                $visa_sql = "select FROM_UNIXTIME(UNIX_TIMESTAMP(date),'%Y-%u') time,seat_name,date,count(*) as count,SUM(pass_refuse_count) as pass_refuse_count,
SUM(refuse_count) refuse_count,SUM(jump_count) jump_count,AVG(avg_deal_time) avg_deal_time,SUM(total_online_time) total_online_time,SUM(total_busy_time) total_busy_time,SUM(total_leave_time) total_leave_time
from fast_visa_report
where date >= '$start' and date <= '$end' {$and_where}
group by time,seat_id";
                break;
            case 'month':
//                $visa_res = DB::table('fast_visa_report')
//                    ->select(DB::raw("FROM_UNIXTIME(UNIX_TIMESTAMP(date),'%Y-%m') as  time,seat_name,count(*) as count,SUM(pass_refuse_count) pass_refuse_count,SUM(refuse_count) refuse_count,SUM(jump_count) jump_count,AVG(avg_deal_time) avg_deal_time,
//SUM(total_online_time) total_online_time,SUM(total_busy_time) total_busy_time,SUM(total_leave_time) total_leave_time "))
//                    ->where('date', '>=', $start.'-01')
//                    ->where('date', '<', date('Y-m-d',strtotime($end.'-01 +1 day')))
//                    ->groupBy('time')
//                    ->orderby('time','asc')
//                    ->skip($page*$limit)
//                    ->take($limit);
                $start = $start.'-01';
                $end = date('Y-m-d',strtotime($end.'+1 month'));
                $page_z = $page * $limit;
                $visa_res_sql  = "SELECT FROM_UNIXTIME(UNIX_TIMESTAMP(date),'%Y-%m') as  time,seat_name,count(*) as count,
SUM(pass_refuse_count) pass_refuse_count,SUM(refuse_count) refuse_count,SUM(jump_count) jump_count,AVG(avg_deal_time) avg_deal_time,
SUM(total_online_time) total_online_time,SUM(total_busy_time) total_busy_time,SUM(total_leave_time) total_leave_time  FROM fast_visa_report
where date >= '{$start}' and date <'{$end}' {$and_where} group by time,seat_id limit {$page_z},{$limit}";
                $visa_res = DB::select($visa_res_sql);
                $visa_sql = "SELECT FROM_UNIXTIME(UNIX_TIMESTAMP(date),'%Y-%m') as  time,seat_name,count(*) as count,
SUM(pass_refuse_count) pass_refuse_count,SUM(refuse_count) refuse_count,SUM(jump_count) jump_count,AVG(avg_deal_time) avg_deal_time,
SUM(total_online_time) total_online_time,SUM(total_busy_time) total_busy_time,SUM(total_leave_time) total_leave_time  FROM fast_visa_report
where date >= '{$start}' and date <='{$end}' {$and_where} group by time,seat_id";
                break;
        }
        $visa_res = $this->format_report_seat($visa_res,$range,$seat_id);
        $visa_count = count(DB::select($visa_sql));
        return ['data' => $visa_res,'count' => $visa_count];
    }
    /**
     * 格式化返回数组(综合统计)
     * @param $visa_res
     * @param $range
     * @return array
     */
    public function format_report_all($visa_res,$range)
    {

        if(!empty($visa_res)){
            foreach ($visa_res as $key=>$value){
                $visa_res[0]['avg_queue_time_total'] +=  $value['avg_queue_time'];
                $visa_res[0]['avg_deal_time_total'] +=  $value['avg_deal_time'];
                $visa_res[0]['avg_online_time_total'] +=  $value['avg_online_time'];
                $visa_res[0]['avg_busy_time_total'] +=  $value['avg_busy_time'];
                $visa_res[0]['avg_leave_time_total'] +=  $value['avg_leave_time'];
                $visa_res[$key]['avg_queue_time'] =  gmdate('H:i:s',$value['avg_queue_time']);
                $visa_res[$key]['avg_deal_time'] =  gmdate('H:i:s',$value['avg_deal_time']);
                $visa_res[$key]['avg_online_time'] =  gmdate('H:i:s',$value['avg_online_time']);
                $visa_res[$key]['avg_busy_time'] =  gmdate('H:i:s',$value['avg_busy_time']);
                $visa_res[$key]['avg_leave_time'] =  gmdate('H:i:s',$value['avg_leave_time']);
                switch ($range){
                    case 'day':
                        break;
                    case 'week':
                        $week = $this->getAWeekTimeSlot($value['date']);
                        $visa_res[$key]['date'] =  'W'.ltrim(strstr($value['time1'],'-'),'-0').'('.$week[0].'~'.$week[1].')';
                        break;
                    case 'month':
                        $visa_res[$key]['date'] =  $value['time'];
                        unset($visa_res[$key]['time']);
                        break;
                }
            }
        }
        return !empty($visa_res)? $visa_res:[];
    }
    /**
     * 格式化返回数组(坐席对比)
     * @param $visa_res
     * @param $range
     * @param $seat_id
     * @return array
     */
    public function format_report_seat($visa_res,$range,$seat_id)
    {

        if(!empty($visa_res)){
            foreach ($visa_res as $key=>$value){
                $visa_res[$key]['avg_deal_time'] =  gmdate('H:i:s',$value['avg_deal_time']);
                $visa_res[$key]['total_online_time'] = $this->time2string($value['total_online_time']);
                $visa_res[$key]['total_busy_time'] =  $this->time2string($value['total_busy_time']);
                $visa_res[$key]['total_leave_time'] =  $this->time2string($value['total_leave_time']);
                switch ($range){
                    case 'day':
                        $visa_res[$key]['on_duty_time'] =  date('Y-m-d H:i:s',$value['on_duty_time']);
                        $visa_res[$key]['off_duty_time'] =  date('Y-m-d H:i:s',$value['off_duty_time']);
                        $visa_res[$key]['time'] =  $value['date'];
                        unset($visa_res[$key]['date']);
                        break;
                    case 'week':
                        $week = $this->getAWeekTimeSlot($value['date']);
                        $visa_res[$key]['on_duty_time'] = '';
                        $visa_res[$key]['off_duty_time'] =  '';
                        $visa_res[$key]['time'] =  'W'.ltrim(strstr($value['time'],'-'),'-0').'('.$week[0].'~'.$week[1].')';
                        $visa_res[$key]['seat_name'] =  $value['seat_name']."({$value['count']}天)";
                        unset($visa_res[$key]['date']);
                        break;
                    case 'month':
                        $visa_res[$key]['on_duty_time'] = '';
                        $visa_res[$key]['off_duty_time'] =  '';
                        $visa_res[$key]['seat_name'] =  $value['seat_name']."({$value['count']}天)";
                        unset($visa_res[$key]['date']);
                        break;
                }
            }
        }
        return !empty($visa_res)? $visa_res:[];
    }
    function time2string($second){
        $day = floor($second/(3600*24));
        $second = $second%(3600*24);//除去整天之后剩余的时间
        $hour = floor($second/3600);
        $second = $second%3600;//除去整小时之后剩余的时间
        $minute = floor($second/60);
        $second = $second%60;//除去整分钟之后剩余的时间
        //返回字符串
//        return $day.'天'.$hour.'小时'.$minute.'分'.$second.'秒';
        return $day.'天'.$hour.':'.$minute.':'.$second;
    }
    /**
     * 获取统计报表合计
     * @param $data
     */
    protected function get_report_total($data)
    {
        $count = count($data);
        if(!$count){
            return [];
        }
        $arr = [
            'date' => '合计', //时间范围
            'pass_refuse_count' => array_sum(array_column($data,'pass_refuse_count')),//面签处理量（通过和拒绝)
            'refuse_count' => array_sum(array_column($data,'refuse_count')),//面签拒绝总数
            'jump_count' => array_sum(array_column($data,'jump_count')),//面签跳过总数
            'avg_queue_time' =>  gmdate('H:i:s',$data[0]['avg_queue_time_total']/$count),//用户排队平均时长
            'avg_deal_time' => gmdate('H:i:s',$data[0]['avg_deal_time_total']/$count),//面签平均处理时长
            'avg_online_time' => gmdate('H:i:s',$data[0]['avg_online_time_total']/$count),//在线平均时长(繁忙和空闲)
            'avg_busy_time' => gmdate('H:i:s',$data[0]['avg_busy_time_total']/$count),//繁忙平均时长
            'avg_leave_time' => gmdate('H:i:s',$data[0]['avg_leave_time_total']/$count),//离开平均时长
        ];
        array_unshift($data,$arr);
        return $data;
    }

    /**
     * 取得给定日期所在周的开始日期和结束日期
     * @param string $gdate 日期，默认为当天，格式：YYYY-MM-DD
     * @param int $weekStart 一周以星期一还是星期天开始，0为星期天，1为星期一
     * @return array 数组array( "开始日期 ",  "结束日期");
     */
    public function getAWeekTimeSlot($gdate = '', $weekStart = 1) {
        if (! $gdate){
            $gdate = date ( "Y-m-d" );
        }
        $w = date ( "w", strtotime ( $gdate ) ); //取得一周的第几天,星期天开始0-6 5
        $dn = $w ? $w - $weekStart : 6; //要减去的天数 4
        $st = date ( "Y-m-d", strtotime ( "$gdate  - " . $dn . "  days " ) );
        $en = date ( "Y-m-d", strtotime ( "$st  +6  days " ) );
        return array ($st, $en ); //返回开始和结束日期
    }

}
