<?php

namespace App\Repositories\Cron;

use App\Fast\FastKey;
use App\Library\Helper;
use App\Models\VideoVisa\ErrorCodeLog;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaLog;
use DB;
use App\Library\Common;
use App\Library\RedisCommon;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\VisaPool;

class SeatOuttimeRepository
{
    protected $redis;
    protected $pool_model;
    protected $seat_manager;
    protected $api_error_email;

    public function __construct()
    {
        $this->api_error_email = config('mail.developer');
        $this->redis = new RedisCommon();
        $this->pool_model = new VisaPool();
        $this->seat_manager = new SeatManage();
    }

    /**
     * 心跳脚本
     * 监控所有状态为busy的坐席，如果30分钟之内没有通过ajax更新最新时间，则将其置为离开。关联的面签单置为重新排队
     */
    public function seatOutTime()
    {
        $keyPre = FastKey::SEAT_KEEP_ALIVE_TIME_KEY;
        $seatObj = new SeatManage();
        $allSeatList = $seatObj->getAll(['*'],
            ['status'=>SeatManage::SEAT_STATUS_ON, 'work_status' => SeatManage::SEAT_WORK_STATUS_BUSY]);
        $redisObj = new RedisCommon();
        $visaModel = new FastVisa();
        $visaLogModel = new FastVisaLog();
        $outTime = 30 * 60;
        $logData = [];
        foreach ($allSeatList as $tempSeatInfo) {
            $tempKey = $keyPre . $tempSeatInfo['id'];
            $tempUpdateInfo = $redisObj->get($tempKey);
            if ($tempUpdateInfo === false) continue;
            if (!isset($tempUpdateInfo['visa_id']) || !isset($tempUpdateInfo['time'])) continue;
            $tempUpdateTime = $tempUpdateInfo['time'];
            $tempVisaId = $tempUpdateInfo['visa_id'];
            //半个小时没有更新心跳了
            if (time() - $tempUpdateTime > $outTime) {
                //强制更新数据
                try {
                    DB::connection('mysql.video_visa')->beginTransaction();

                    //获取visa  todo tanrenzong 以后放在外面获取
                    $visa = $visaModel->getOne(['*'],['id'=>$tempVisaId]);
                    if ($visa) {
                        if($visa['reconsideration_status'] == FastVisa::VISA_RECONSIDERATION_STATUS_DOING){//复议订单
                            $visaModel->updateBy(['reconsideration_status' => FastVisa::VISA_RECONSIDERATION_STATUS_CAN],['id'=>$tempVisaId]);
                        }else{
                            //更新visa状态为重新排队
                            $visaUpdateRet = $visaModel->updateVisaStatus(FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT, ['id'=>$tempVisaId]);
                        }
                        //删除订单队列该订单
                        $redisObj->zRem(config('common.auto_apply_order_key'),$tempVisaId);
                        //插入新的visa log
                        $newVisaLogData = [
                            'visa_id' => $tempVisaId,
                            'master_id' => $visa['master_id'],
                            'queuing_time' => time(),
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                        $visaLogModel->insertVisaLog($newVisaLogData);
                    }

                    //更新坐席状态为离开
                    $seatUpdateRet = $seatObj->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_LEAVE, ['id' => $tempSeatInfo['id']]);
                    DB::connection('mysql.video_visa')->commit();
                    $logData[] = [
                        'visa_id'=>$tempVisaId,
                        'seat_id'=>$tempSeatInfo['id'],
                        'pre_update_time'=> $tempUpdateTime,
                        'cur_time'=>time(),
                        'visa_update_ret'=> isset($visaUpdateRet) ? $visaUpdateRet : -1,
                        'seat_update_ret' =>$seatUpdateRet
                    ];

                    //清空心跳
                    $seatObj->updateKeepAliveKey($tempSeatInfo['id']);
                }catch (\Exception $e){
                    DB::connection('mysql.video_visa')->rollback();
                    $msg = $e->getMessage();
                    $trace = $e->getTraceAsString();
                    @Common::sendMail('心跳超时异常','参数：坐席id'.$tempSeatInfo['id'].'面签池id'.$tempKey . '<p>msg:' . $msg . '<p>trace:' . $trace , $this->api_error_email);
                } finally {

                }
            }
        }
        if (!empty($logData)) (new ErrorCodeLog())->runLog(config('errorLogCode.seatOutTime') ,$logData);

        echo "执行完毕\n";
    }

    /**
     * 从监控数组中移除
     * @param $visaId
     */
    public function deleteFromMonitorList($visaId)
    {
        $common = config('common');
        $seat_agree_time_key = $common['seat_agree_time_key'];
        $seat_agree_time = $common['seat_agree_time'];
        $seat_agree_time_val = $this->redis->get($seat_agree_time_key);
        unset($seat_agree_time_val[$visaId]);
        $this->redis->setex($seat_agree_time_key, $seat_agree_time_val, $seat_agree_time);
    }
}