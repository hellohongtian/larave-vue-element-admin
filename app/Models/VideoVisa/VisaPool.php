<?php
namespace App\Models\VideoVisa;

/**
 * 面签信审
 */
class VisaPool extends VideoVisaModel {
	protected $table = 'visa_pool';
	public $timestamps = false;

	public $symbol = [
		'applyid' => '=',
		'userid' => '=',
		'mobile' => '=',
		'seat_id' => '=',
		'status' => 'in',
		'queuing_time' => '>=',
		'end_video_time' => '>=',
		'is_hang' => '=',
		'create_time' => '>=',

	];

	public function getCount($where) {
		$count = $this->where(function ($query) use ($where) {
			foreach ($where as $key => $value) {
				if (!empty($value)) {
					if ($this->symbol[$key] == 'like') {
						$query->where($key, $this->symbol[$key], '%' . $value . '%');
					} elseif ($this->symbol[$key] == 'in') {
						$query->whereIn($key, $value);
					} else {
						$query->where($key, $this->symbol[$key], $value);
					}
				}
			}
		})->count();
		return $count;
	}

	//获取平均接通到结束通话时长
	public function getAvgStartToEndTimt($where) {
		$res = $this->selectRaw("avg(end_video_time-call_video_time) as avgstarttoend")->where(function ($query) use ($where) {
			foreach ($where as $key => $value) {
				if (!empty($value)) {
					if ($this->symbol[$key] == 'like') {
						$query->where($key, $this->symbol[$key], '%' . $value . '%');
					} elseif ($this->symbol[$key] == 'in') {
						$query->whereIn($key, $value);
					} else {
						$query->where($key, $this->symbol[$key], $value);
					}
				}
			}
		})->get()->toArray();
		if (!empty($res)) {
			$res = intval($res[0]['avgstarttoend']);
		} else {
			$res = 0;
		}
		return $res;
	}

}