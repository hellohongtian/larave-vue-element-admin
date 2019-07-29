<?php
namespace App\Repositories;

use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\VisaRemark;
use App\Models\VideoVisa\VisaPool;
use App\Library\RedisCommon;
use Illuminate\Support\Facades\DB;

class ApplyRepository {
    /**
     * 计算等待时长
     * @param $visaInfo
     * @param string $queuingTime
     * @return int
     */
    public function getApplyWaitTime($visaInfo, $queuingTime = '')
    {
        $status = $visaInfo['status'];
        $masterId = $visaInfo['master_id'];
        $queuingTime = $queuingTime ? $queuingTime : time();
        $visaModel = new FastVisa();
        $seatModel = new SeatManage();
        $waitMin = $beforeCount = 0;
        if (!in_array($status,[FastVisa::VISA_STATUS_IN_SEAT, FastVisa::VISA_STATUS_IN_VIDEO, FastVisa::VISA_STATUS_HANG])) {
            //查询在线坐席数量
            $onlineSeatCount = $seatModel->where('status',SeatManage::SEAT_STATUS_ON)
                ->whereIn('work_status', [SeatManage::SEAT_WORK_STATUS_FREE,SeatManage::SEAT_WORK_STATUS_BUSY])->count();
            $onlineSeatCount = empty($onlineSeatCount)? 1:$onlineSeatCount;
            //查询前面订单数
            $beforeCount = $visaModel->where('line_up_time', '<', $queuingTime)->where('line_up_time', '>=', strtotime(date('Y-m-d')))
                ->whereIn('status', [FastVisa::VISA_STATUS_IN_QUEUEING,FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK,FastVisa::VISA_STATUS_HANG_QUEUEING])->count();
            $beforeCount = empty($beforeCount)? 0:$beforeCount;
            //排在前面的面签单数量 * 10
            $waitMin = intval($beforeCount * 10 / $onlineSeatCount);
        }

        return $waitMin ? $waitMin : 5;
    }
}