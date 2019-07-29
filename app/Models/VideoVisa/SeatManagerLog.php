<?php
namespace App\Models\VideoVisa;
use App\Library\Helper;

/**
 * 面签记录表
 */
class SeatManagerLog extends VideoVisaModel {

	protected $table = 'seat_manager_log';
	public $timestamps = false;

	/**
	 * 获取坐席各个状态时间统计
	 * @param $startDate
	 * @param $endDate
	 * @param int $seatId
	 * @return array
	 */
	public function getAvgStatusTime($startDate, $endDate, $seatId = 0) {
        $seat_ids = [];
		$query = $this->select('type', 'duration_time','seat_id');
		if ($seatId > 0) {
			$query->where('seat_id', '=', $seatId);
		}
		if($startDate == $endDate){
            $query->where('op_date', '=', $startDate);
        }else{
            $query->where('op_date', '>=', $startDate);
            $query->where('op_date', '<=', $endDate);
        }
		$res = $query->get()->toArray();
//        dd($query->toSql(),$query->getBindings());

		$result = [
			'free' => 0, //平均空闲时长
			'busy' => 0, //平均繁忙时长
			'leave' => 0, //平均离开时长
			'off_line' => 0,//平均离线时长
			'avg_online' => 0, //平均在线时长
		];
		if ($res) {
			foreach($res as $each) {
			    $seat_ids[$each['seat_id']] = 1;
				switch($each['type']) {
				    //空闲
					case 1:
						$result['free'] += $each['duration_time'];
						break;
                    //繁忙
					case 2:
						$result['busy'] += $each['duration_time'];
						break;
					//离开
					case 3:
						$result['leave'] += $each['duration_time'];
						break;
					//离线
					case 4:
						$result['off_line'] += $each['duration_time'];
						break;
					default:
						break;
				}
			}
            $count_seat = count($seat_ids);
			//计算天数
			$dayNum = count(Helper::dates_between($startDate, $endDate));
			//算出平均值
			$result['avg_online'] = $result['free'] + $result['busy'] + $result['leave'];
			foreach($result as &$each) {
				$each = $each != 0 ? gmdate("H:i:s", $each / $dayNum / $count_seat) : 0;
			}
		}

		return $result;
	}
}
?>