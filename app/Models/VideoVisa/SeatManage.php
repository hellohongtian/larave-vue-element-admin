<?php 
namespace App\Models\VideoVisa;
use App\Fast\FastKey;
use App\Library\Common;
use App\Library\RedisCommon;
use App\Library\RedisObj;
use Illuminate\Support\Facades\Event;

/**
* 坐席管理
*/
class SeatManage extends VideoVisaModel
{

	protected $table='seat_manager';
	public $timestamps=false;


    //坐席工作状态
    const SEAT_WORK_STATUS_FREE = 1;
    const SEAT_WORK_STATUS_BUSY = 2;
    const SEAT_WORK_STATUS_LEAVE = 3;
    const SEAT_WORK_STATUS_OFFLINE = 4;

    //状态中文对照
    public static $statusNameList = [
        self::SEAT_WORK_STATUS_FREE => '空闲',
        self::SEAT_WORK_STATUS_BUSY => '繁忙',
        self::SEAT_WORK_STATUS_LEAVE => '离开',
        self::SEAT_WORK_STATUS_OFFLINE => '离线',
    ];
    //坐席是否可用
    const SEAT_STATUS_ON = 1;
    const SEAT_STATUS_OFF = 2;


    /**
     * 更改坐席状态。--更改坐席状态必须走这个函数！
     * @param $status
     * @param $where
     * @param array $extraFields
     * @return mixed
     */
    public function updateWorkSeatStatus($status, $where, $extraFields = [])
    {
        $updateData['work_status'] = $status;

        if ($extraFields) {
            foreach ($extraFields as $fieldName => $value) {
                $updateData[$fieldName] = $value;
            }
        }
        $result = $this->updateBy($updateData, $where);
        //更改坐席状态，触发时间记录事件
//        if(is_production_env() && $result && isset($where['id'])){
//            Event::fire(new \App\Events\SeatStatusUpdateEvent($where['id'], $status));
//        }
        if($result || !is_production_env()){
           Event::fire(new \App\Events\SeatStatusUpdateEvent($where['id'], $status));
        }
        return $result;
    }

    /**
     * 更新 坐席心跳
     * @param $seatId
     * @param null $visaId
     */
    public function updateKeepAliveKey($seatId, $visaId = null)
    {
        $tempSeatKey = FastKey::SEAT_KEEP_ALIVE_TIME_KEY . $seatId;
        if (empty($visaId)) {
            $value = ['time'=>time()];
        } else {
            $value = ['visa_id' => $visaId, 'time'=>time()];
        }
        RedisObj::instance()->setex($tempSeatKey, $value, 86400);
    }

    /**
     * 获取坐席正在处理中的单子的id,没有则返回0
     * @param $seatId
     * @return int
     */
    public function getHandingVisaId($seatId)
    {
        $tempSeatKey = FastKey::SEAT_KEEP_ALIVE_TIME_KEY . $seatId;
        $handingInfo = RedisObj::instance()->get($tempSeatKey);
        return isset($handingInfo['visa_id']) ? $handingInfo['visa_id'] : 0;
    }

    public function getSeatIdToFullNameArr()
    {
        $allSeat = $this->getAll(['id','fullname']);
        $result = array_combine(array_column($allSeat,'id'), array_column($allSeat,'fullname'));
        return $result;
    }

    public  function get_effective_seat()
    {
        $allSeat = $this->getAll(['id','fullname'],['status' => 1,'flag' => Role::FLAG_FOR_RISK]);
        $result = array_combine(array_column($allSeat,'id'), array_column($allSeat,'fullname'));
        return $result;
    }

    /**
     * @note 用于导出完整名字,带后面的数字
     */
    public function get_export_name()
    {
        $allSeat = $this->getAll(['id','fullname','mastername']);
        foreach ($allSeat as $key => $value){
            if(preg_match('/\d+/',$value['mastername'],$arr) && !preg_match('/\d+/',$value['fullname'],$arr2)){
                $allSeat[$key]['fullname'] = $value['fullname'].$arr[0];
            }
        }
        $result = array_combine(array_column($allSeat,'id'), array_column($allSeat,'fullname'));
        return $result;
    }
}
 ?>