<?php
namespace App\Models\VideoVisa;
use Illuminate\Support\Facades\DB;

/**
 * 面签结果表
 */
class FastVisaResult extends VideoVisaModel
{

    protected $table = 'fast_visa_result';
    public $timestamps = false;

    const VISA_RESULT_STATUS_EXCEPTION = -1;
    const VISA_RESULT_STATUS_TIMEOUT = -2;

    //数值尽量和fast_visa.status保持一致
    const VISA_RESULT_STATUS_AGREE = 5;
    const VISA_RESULT_STATUS_REFUSE = 6;
    const VISA_RESULT_STATUS_SKIP = 7;
    const VISA_RESULT_STATUS_IN_QUEUE_AND_SEAT = 8;
    const VISA_RESULT_STATUS_HANG = 10;

    public function getVisaRet($visaId, $seatId, $masterId)
    {
        return $this->getOne(['inside_opinion', 'out_opinion','need_verify'], ['visa_id'=>$visaId, 'seat_id'=>$seatId, 'master_id'=>$masterId],['id'=>'desc']);
    }

    public function getHistoryResultByVisaId($visaId)
    {
        $fields = ['seat_id','visa_status','inside_opinion','out_opinion','updated_at','created_at'];
        $where = ['visa_id'=>$visaId, 'in' => ['visa_status'=>FastVisa::$visaFinishedStatusList]];
        $visaResultList = (new FastVisaResult())->getAll($fields, $where, ['id'=>'desc']);
        if ($visaResultList) {
            $seatIdToSeatNameArr = (new SeatManage())->getSeatIdToFullNameArr();
            foreach($visaResultList as &$each) {
                $each['seat_name'] = isset($seatIdToSeatNameArr[$each['seat_id']]) ? $seatIdToSeatNameArr[$each['seat_id']] : $each['seat_id'];
            }
        }
        return $visaResultList;
    }

    /**
     * 新增visaResult
     * @param $insertData
     * @return mixed
     */
    public function insertVisaResult($insertData)
    {
        return $this->insertGetId($insertData);
    }


    public function updateVisaResult($updateData, $where)
    {
        return $this->updateBy($updateData, $where);
    }

}