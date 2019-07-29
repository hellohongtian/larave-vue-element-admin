<?php
namespace App\Repositories\Cron;


use App\Fast\FastException;
use App\Fast\FastKey;
use App\Library\Helper;
use App\Models\VideoVisa\ErrorCodeLog;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\VisaPool;
use App\Models\VideoVisa\VisaRemark;
use App\Library\RedisCommon;
use Illuminate\Support\Facades\DB;
use App\Models\VideoVisa\SeatManage;
use App\Library\Common;
use App\Repositories\ApplyRepository;

class ApplyQuqeRepository {


    protected $pool;
    protected $remark;
    protected $redis;
    protected $common;

    public function __construct() {
        $this->pool = new VisaPool();
        $this->remark = new VisaRemark();
        $this->redis = new RedisCommon();
        $this->common = new Common();
    }


    /**
     * 将发起排队的订单，转换为可领取 status:2转换成9
     */
    public function manageQueue(){
        $visaObj = new FastVisa();
        //获取排队中订单
        $inQueueVisaList = $visaObj->getAll(['id','status','master_id'],['status'=>FastVisa::VISA_STATUS_IN_QUEUEING, 'updated_at >='=> date('Y-m-d')]);
        //获取3处理中(已领取未视频) 4视频中 9可派发可领取  11挂起排队 订单
        $inHandleVisaList = $visaObj->getAll(['id','status','master_id'],['in' =>['status'=>FastVisa::$canJumpToVideoStatusList], 'updated_at >=' => date('Y-m-d')]);
        $inHandleMasterIdList = array_column($inHandleVisaList, 'master_id');

        $needHandleList = [];
        foreach ($inQueueVisaList as $tempVisaInfo) {
            $tempMasterId = $tempVisaInfo['master_id'];
            //如果销售masterid已经存在3处理中(已领取未视频) 4视频中 9可派发可领取  11挂起排队 订单，则不允许排队
            if (in_array($tempMasterId, $inHandleMasterIdList)) continue;

            if (!isset($needHandleList[$tempMasterId]))
                $needHandleList[$tempMasterId] = $tempVisaInfo['id'];
        }

        $needHandleIdList = array_values($needHandleList);
        $where = ['in' => ['id'=>$needHandleIdList]];
        $visaObj->updateVisaStatus(FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK, $where);
        return ['queueList'=>$inQueueVisaList, 'handleList'=>$inHandleVisaList, 'updateList'=>$needHandleIdList];
    }


    /**
     * 自动分单
     * 将排队中的订单自动分给坐席
     */
    public function autoAllotApply()
    {
        
        $visaObj = new FastVisa();
        $seatObj = new SeatManage();
        $visaLogModel = new FastVisaLog();

        //获取所有的可领取/可派单的订单 + 挂起订单
        $visaWhere = [
            'line_up_time >' => time() - 3600*8, //只获取8个小时内排队的，
            'status' => FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK,
        ];
        $canAllotVisaList = $visaObj->getAll(['id', 'master_id', 'line_up_time', 'seat_id', 'status'], $visaWhere, ['line_up_time'=>'asc']);
        //以master为基准，组建每个masterId可派单的id
        $masterIdToVisaList = [];
        foreach ($canAllotVisaList as $visaInfo) {
            $masterId = $visaInfo['master_id'];
            //选出每个坐席应该处理的一条订单
            if (!isset($masterIdToVisaList[$masterId]) || $masterIdToVisaList[$masterId]['line_up_time'] > $visaInfo['line_up_time']) {
                $masterIdToVisaList[$masterId] = $visaInfo;
            }
        }

        //以申请排队时间，重新排队
        array_multisort(array_column($masterIdToVisaList, 'line_up_time'), SORT_ASC, $masterIdToVisaList);

        //所有坐席
        $allSeatList = $seatObj->getAll(['id', 'fullname']);
        $allSeatNameList = array_combine(array_column($allSeatList,'id'), array_column($allSeatList, 'fullname'));

        //空闲坐席
        $freeSeatList = $seatObj->getAll(['id'], ['status'=>SeatManage::SEAT_STATUS_ON, 'work_status' => SeatManage::SEAT_WORK_STATUS_FREE], ['updated_at'=>'asc']);
        $freeSeatList = array_combine(array_column($freeSeatList, 'id'), array_column($freeSeatList, 'id'));

        //繁忙坐席
        $busySeatList = $seatObj->getAll(['id'], ['work_status' => SeatManage::SEAT_WORK_STATUS_BUSY]);
        $busySeatList = array_combine(array_column($busySeatList, 'id'), array_column($busySeatList, 'id'));

        $logAllotList = [];

        echo "busy seat list :" . print_r($busySeatList, true);
        echo "free seat list :" . print_r($freeSeatList, true);
        echo "master to visa list :" . print_r($masterIdToVisaList, true);
        foreach ($masterIdToVisaList as $masterId => $visa) {

            $visaSeatId = $visa['seat_id'];

            //如果关联有坐席（说明是之前坐席挂起的），如果该坐席繁忙，则跳过
            if (in_array($visaSeatId, $busySeatList)) {
                continue;
            }

            //分配一个空闲坐席
            if (isset($freeSeatList[$visaSeatId])) {
                $allotSeatId = $freeSeatList[$visaSeatId];
                unset($freeSeatList[$visaSeatId]);
            } else {
                $allotSeatId = array_shift($freeSeatList);
            }
            //已经没有可分配的空闲坐席
            if (empty($allotSeatId)) {
                break;
            }
            //锁定visa
            if (FastVIsa::lockVisa($visa['id']) ) {
                FastException::throwException('visa已被锁定，visa id : ' . $visa['id']);
            }
            try {
                DB::connection('mysql.video_visa')->beginTransaction();
                //更新visa
                $visaWhere = ['id' => $visa['id'], 'status' => $visa['status']];
                $extraUpdateData['seat_id'] = $allotSeatId;
                $extraUpdateData['seat_name'] = $allSeatNameList[$allotSeatId];
                //更新为处理中
                $visaRet = $visaObj->updateVisaStatus(FastVisa::VISA_STATUS_IN_SEAT, $visaWhere, $extraUpdateData);
                if (!$visaRet) {
                    FastException::throwException('更新visa失败');
                }

                //更新visa_log
                $latestVisaLog = $visaLogModel->getOne(['*'], ['visa_id'=>$visa['id']], ['id'=>'desc']);
                if (!$latestVisaLog) {
                    FastException::throwException('数据异常，查询不到最新的fast_visa_log日志，visa_id:' . $visa['id']);
                }
                $visaLogUpdateData['seat_id'] = $allotSeatId;
                $visaLogUpdateData['updated_at'] = date('Y-m-d H:i:s');
                $visaLogUpdateData['seat_receive_time'] = time();
                $exeUpdate = $visaLogModel->updateVisaLog($visaLogUpdateData, ['id'=>$latestVisaLog['id']]);
                if (!$exeUpdate) {
                    FastException::throwException('更新fast_visa_log日志失败, id:'.$latestVisaLog['id']);
                }

                //更新坐席为繁忙
                $seatWhere = ['id' => $allotSeatId, 'work_status' => SeatManage::SEAT_WORK_STATUS_FREE];
                $seatRet = $seatObj->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_BUSY, $seatWhere);
                if (!$seatRet) {
                    FastException::throwException('更新seat失败');
                }

                //关联心跳
                //$seatObj->updateKeepAliveKey($allotSeatId, $visa['id']);
                $logAllotList[] = ['seat_id'=>$allotSeatId, 'visa_id'=>$visa['id']];
                //解锁visa
                FastVisa::unLockVisa($visa['id']);

                DB::connection('mysql.video_visa')->commit();

                //通知前端坐席订单已分配
                $send_msg = Common::notifyFontNewVisa($allotSeatId, FastVisa::NOTIFY_SEAT_NEW_VISA);

                if($send_msg !== true){
                    (new ErrorCodeLog())->runLog(11111, $send_msg);
                }
            } catch (FastException $e) {
                FastVisa::unLockVisa($visa['id']);
                echo $e->getMessage();
                array_unshift($freeSeatList, $allotSeatId);
                DB::connection('mysql.video_visa')->rollback();
            }
        }

        echo "log:" . print_r($logAllotList, true);
        echo "\n执行完毕\n";

        return $logAllotList;
    }

    /**
     * 自动分单
     * 将排队中的订单自动分给坐席
     */
    public function autoAllotApplyNew()
    {
        //锁定进程，避免多次执行
        if(Common::redis_lock(config('common.fast_cron_lock'))){
            return [];
        }
        /*********查询所有状态为2，9，11的订单，匹配相应坐席，状态为11的只匹配对应的坐席，如为匹配到坐席的状态为2的订单，状态置为9***********/
        $visaWhere = [
            'line_up_time >' => time() - 3600*8, //只获取8个小时内排队的，
            'in'=>['status'=>[FastVisa::VISA_STATUS_IN_QUEUEING,FastVisa::VISA_STATUS_HANG_QUEUEING,FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK]]
        ];
        $visaObj = new FastVisa();
        $canAllotVisaList = $visaObj->getAll(['id', 'master_id', 'line_up_time', 'seat_id', 'status'], $visaWhere, ['line_up_time'=>'asc']);
        if(empty($canAllotVisaList)){
            return '';
        }
        $seatObj = new SeatManage();
        /*********查询所有空闲坐席名单***********/
        $freeSeatList = $seatObj->getAll(['id'], ['status'=>SeatManage::SEAT_STATUS_ON, 'work_status' => SeatManage::SEAT_WORK_STATUS_FREE], ['updated_at'=>'asc']);
        $freeSeatList = array_column($freeSeatList, 'id');
        $free_count = count($freeSeatList);
        $free_status = array_flip($freeSeatList);
        /***********如果销售masterid已经存在3处理中(已领取未视频) 4视频中 9可派发可领取  11挂起排队 订单，则不允许排队*******/
        $inHandleVisaList = $visaObj->getAll(['id','status','master_id'],['in' =>['status'=>FastVisa::$canJumpToVideoStatusList], 'updated_at >=' => date('Y-m-d')]);
        $inHandleMasterIdList = array_unique(array_column($inHandleVisaList, 'master_id'));
        $needChangeStatus = $seat_match_arr = [];
        foreach($canAllotVisaList as $key => $value){
            $masterId = $value['master_id'];
            if(!empty($free_count) && !empty($freeSeatList) && count($seat_match_arr) < $free_count){//还存在空闲坐席，订单可匹配坐席
                //挂起订单 坐席非空闲
                if($value['status'] == FastVisa::VISA_STATUS_HANG_QUEUEING && !isset($free_status[$value['seat_id']])){
                    continue;
                }
                //挂起订单，空闲坐席已分配订单
                if($value['status'] == FastVisa::VISA_STATUS_HANG_QUEUEING && isset($seat_match_arr[$value['seat_id']]) && $value['line_up_time'] >= $seat_match_arr[$value['seat_id']]['line_up_time']){
                    continue;
                }
                //挂起排队订单
                if($value['status'] == FastVisa::VISA_STATUS_HANG_QUEUEING && isset($value['seat_id'])){
                    //坐席已分配非挂起排队订单
                    if(isset($seat_match_arr[$value['seat_id']]) && $seat_match_arr[$value['seat_id']]['status'] != FastVisa::VISA_STATUS_HANG_QUEUEING){
                        $seat_id = array_shift($freeSeatList);
                        $seat_match_arr[$seat_id] = array_merge($seat_match_arr[$value['seat_id']],['seat_id' => $seat_id]);
                        $seat_match_arr[$value['seat_id']] = $value;
                    }else{
                        $seat_match_arr[$value['seat_id']] = $value;
                        $freeSeatList = array_diff($freeSeatList,[$value['seat_id']]);
                    }
                }else{
                    if(!isset($seat_match_arr[$value['seat_id']])){
                        $seat_id = array_shift($freeSeatList);
                        $arr = array_merge($value,['seat_id'=>$seat_id]);
                        $seat_match_arr[$seat_id] = $arr;
                    }
                }
            }else{
                //更改为可转发可领取9
                if(!in_array($masterId, $inHandleMasterIdList) &&  !isset($needChangeStatus[$masterId])){
                    $needChangeStatus[$masterId] = $value['id'];
                }
            }

        }
        /********排队中更新为可派发可领取，每个master_id只能有一个处理订单。******/
        if(!empty($needChangeStatus)){
            $needChangeStatus = array_values($needChangeStatus);
            $where = ['in' => ['id'=>$needChangeStatus]];
            $visa_res = $visaObj->updateVisaStatus(FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK, $where);
            unset($needChangeStatus);
            if (!$visa_res) {
                FastException::throwException('更新visa,排队中更新为可派发可领取失败');
            }
        }
        //所有坐席名
        $allSeatList = $seatObj->getAll(['id', 'fullname']);
        $allSeatNameList = array_combine(array_column($allSeatList,'id'), array_column($allSeatList, 'fullname'));
        $visaLogModel = new FastVisaLog();
        /*********根据空闲坐席数提取对应数量的订单，一一匹配上。***********/
        foreach ($seat_match_arr as $seat => $visa) {
            if (FastVIsa::lockVisa($visa['id'])) {
                continue;
            }
            try {
                $visaSeatId = $visa['seat_id'];
                DB::connection('mysql.video_visa')->beginTransaction();
                $visaWhere = ['id' => $visa['id'], 'status' => $visa['status']];
                $extraUpdateData['seat_id'] = $visaSeatId;
                $extraUpdateData['seat_name'] = $allSeatNameList[$visaSeatId];
                //更新为处理中
                $visaRet = $visaObj->updateVisaStatus(FastVisa::VISA_STATUS_IN_SEAT, $visaWhere, $extraUpdateData);
                if (!$visaRet) {
                    FastException::throwException('更新visa失败');
                }
                //更新visa_log
                $latestVisaLog = $visaLogModel->getOne(['*'], ['visa_id' => $visa['id']], ['id' => 'desc']);
                if (!$latestVisaLog) {
                    FastException::throwException('数据异常，查询不到最新的fast_visa_log日志，visa_id:' . $visa['id']);
                }
                $visaLogUpdateData['seat_id'] = $visaSeatId;
                $visaLogUpdateData['updated_at'] = date('Y-m-d H:i:s');
                $visaLogUpdateData['seat_receive_time'] = time();
                $exeUpdate = $visaLogModel->updateVisaLog($visaLogUpdateData, ['id' => $latestVisaLog['id']]);
                if (!$exeUpdate) {
                    FastException::throwException('更新fast_visa_log日志失败, id:' . $latestVisaLog['id']);
                }
                //更新坐席为繁忙
                $seatWhere = ['id' => $visaSeatId, 'work_status' => SeatManage::SEAT_WORK_STATUS_FREE];
                $seatRet = $seatObj->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_BUSY, $seatWhere);
                if (!$seatRet) {
                    FastException::throwException('更新seat失败');
                }
                DB::connection('mysql.video_visa')->commit();
                //通知前端坐席订单已分配
                $send_msg = Common::notifyFontNewVisa($visaSeatId, FastVisa::NOTIFY_SEAT_NEW_VISA);
                if ($send_msg !== true) {
                    (new ErrorCodeLog())->runLog(11111, $send_msg);
                }
                DB::connection('mysql.video_visa')->commit();
                FastVisa::unLockVisa($visa['id']);
            }catch (FastException $e){
                FastVisa::unLockVisa($visa['id']);
                DB::connection('mysql.video_visa')->rollback();
            }
        }
        Common::redis_lock(config('common.fast_cron_lock'),true);
    }


    /**
     * 凌晨清空队列。将所有状态非结束的面签单置为重新排队
     * @return bool
     */
    public function clearQueue()
    {
        echo "开始执行\n";

        $visaModel = new FastVisa();
        $seatModel = new SeatManage();
        $redis_obj = new RedisCommon();
        //清空redis队列
        $redis_obj->zRemRangeByScore(config('common.auto_apply_seat_key'),0,100000000000);
        $redis_obj->zRemRangeByScore(config('common.auto_apply_order_key'),0,100000000000);
        //获取所有已发起排队但是又没有结果的
        $statusList = [
            FastVisa::VISA_STATUS_IN_QUEUEING,
            FastVisa::VISA_STATUS_IN_SEAT,
            FastVisa::VISA_STATUS_IN_VIDEO,
            FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT,
            FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK,
            FastVisa::VISA_STATUS_HANG,
        ];
        $visaList = $visaModel->getAll(['*'], ['in'=>['status'=>$statusList]]);
        if ($visaList) {
            $visaIds = array_column($visaList, 'id');

            //更新为排队,坐席置为空。
            $extraUpdateFields = ['seat_id'=>0, 'seat_name'=>''];
            $where = ['in'=>['id'=>$visaIds]];
            $visaModel->updateVisaStatus(FastVisa::VISA_STATUS_NOT_IN_QUEUE, $where, $extraUpdateFields);

        }
        //获取挂起排队订单，置为10
        $hang_statusList = [
            FastVisa::VISA_STATUS_HANG_QUEUEING
        ];
        $hang_list = $visaModel->getAll(['*'], ['in'=>['status'=>$hang_statusList]]);
        if(!empty($hang_list)){
            $all_hang_id = array_column($hang_list, 'id');
            $where = ['in'=>['id'=>$all_hang_id]];
            $visaModel->updateVisaStatus(FastVisa::VISA_STATUS_HANG, $where);
        }
        //获取所有坐席id
        $allSeatIds = $seatModel->getAll(['id'], ['status' => 1]);
        $allSeatIds = array_column($allSeatIds, 'id');

        //更新所有坐席状态为离开
        $seatModel->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_OFFLINE, ['id >' => 0]);

        //清空所有心跳
        if ($allSeatIds) {
            foreach ($allSeatIds as $seatId) {
                $seatModel->updateKeepAliveKey($seatId);
            }
        }

        echo "执行结束\n";
    }
}