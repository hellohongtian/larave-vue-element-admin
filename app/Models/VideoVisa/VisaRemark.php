<?php 
namespace App\Models\VideoVisa;

/**
* 面签记录表
*/
class VisaRemark extends VideoVisaModel
{
	protected $table='visa_remark';
	public $timestamps=false;


	private $pagesize = 20;
	public $symbol = [
		'status' => '=',
		'carid' => '=',
		'channel' => '=',
		'applyid' => '=',
		'fullname' => 'like',
		'car_name' => 'like',
		'business_type' => '=',
		'status_all' => 'in',
		'create_time' => '>=',
		'end_time' => '<=',
		'seat_id' => '=',
		'visa_time' => '>=',
	];
	public function getVideoList($where = [], $fields = '*') {
		$res = $this::select($fields)->where(function ($query) use ($where) {
			foreach ($where as $key => $value) {
				if (!empty($value)) {
					if ($this->symbol[$key] == 'like') {
						$query->where($key, $this->symbol[$key], '%' . $value . '%');
					} else {
						$query->where($key, $this->symbol[$key], $value);
					}

				}

			}
		})
			->whereIn('status', [5, 6, 8])
			->orderBy('id', 'asc')
			->paginate($this->pagesize);
		return $res;
	}
	/**
	 * 获取 5审合通过  6拒绝  7跳过 8重新排队的总数据
	 * @param  array  $where [description]
	 * @return [type]        [description]
	 */
	public function getVisaMap($where = []) {
		$res = $this::selectRaw("status,seat_id,seat_name,count(id) as count")->where(function ($query) use ($where) {
			foreach ($where as $key => $value) {
				if (!empty($value)) {
					if ($this->symbol[$key] == 'like') {
						$query->where($key, $this->symbol[$key], '%' . $value . '%');
					} elseif ($key == 'status_all') {
						$query->whereIn('status', $value);
					} elseif ($key == 'start_time') {
						$query->where('create_time', '>=', $value);
					} elseif ($key == 'end_time') {
						$query->where("create_time", '<=', $value);
					} else {
						$query->where($key, $this->symbol[$key], $value);
					}

				}

			}
		})->groupBy('status')
			->get()->toArray();
		$count = 0;
		foreach ($res as $k => $v) {
			$count += $v['count'];
		}
		$result['count'] = $count;
		$result['data'] = $res;
		unset($res);
		return ($result);
	}

	/**
	 * 获取全部坐席 或者指定人  在指定H间段的相关数据总量
	 * @param  array  $where [description]
	 * @return [type]        [description]
	 */
	public function getComprehensiveData($where = []) {
		$result = [];
		// 获取 5通过  6拒绝 7跳过总数
		$result['audit_pass_reject_jump'] = $this->selectRaw('count('.$this->table.'.id) as total')
			->leftJoin('visa_remark_attach','visa_remark.id','=','visa_remark_attach.remark_id')
			->whereIn($this->table.'.'.'status', [5, 6, 7])
			->where(function ($query) use ($where) {
				foreach ($where as $key => $value) {
					if (!empty($value)) {
						if ($this->symbol[$key] == 'like') {
							$query->where($this->table.'.'.$key, $this->symbol[$key], '%' . $value . '%');
						} elseif ($key == 'start_time') {
							$query->where($this->table.'.'.'create_time', '>=', $value);
						} elseif ($key == 'end_time') {
							$query->where($this->table.'.'."create_time", '<=', $value);
						}elseif ($key == 'visa_time') {
							$query->where("visa_remark.visa_time", '<=', $value);
						} else {
							$query->where($this->table.'.'.$key, $this->symbol[$key], $value);
						}
					}
				}
			})
			->count();
		//排队等待总H长
		$arr = $this->selectRaw("count(visa_remark.id) as countnum,count(visa_remark_attach.queue_to_receive_time) as alltime")//,sum(seat_receive_time-queuing_time) as alltime
		->leftJoin('visa_remark_attach','visa_remark.id','=','visa_remark_attach.remark_id')
			->where(function ($query) use ($where) {
				foreach ($where as $key => $value) {
					if (!empty($value)) {
						if ($this->symbol[$key] == 'like') {
							$query->where($this->table.'.'.$key, $this->symbol[$key], '%' . $value . '%');
						} elseif ($key == 'start_time') {
							$query->where($this->table.'.'.'create_time', '>=', $value);
						} elseif ($key == 'end_time') {
							$query->where($this->table.'.'."create_time", '<=', $value);
						}elseif ($key == 'visa_time') {
							$query->where("visa_remark.visa_time", '<=', $value);
						} else {
							$query->where($this->table.'.'.$key, $this->symbol[$key], $value);
						}
					}
				}
			})->get()->toArray();

		if (!empty($arr[0]['alltime'] && !empty($arr[0]['countnum']))) {
			$avg_time = intval($arr[0]['alltime'] / $arr[0]['countnum']);
//			$hours = 0;
//			$minutes = 0;
//			$second = 0;
//			$result['avg_time_long'] = ''; // 用户排队平均H长
//			if ($avg_time >= 3600) {
//				$hours = intval($avg_time / 3600);
//				$minutes = intval(($avg_time % 3600) / 60);
//				$second = intval(($avg_time % 3600) % 60);
//				$result['avg_time_long'] = $hours . 'h' . $minutes . 'm' . $second . 's';
//
//			} elseif ($avg_time > 60) {
//				$minutes = intval(($avg_time % 3600) / 60);
//				$second = intval(($avg_time % 3600) % 60);
//				$result['avg_time_long'] = $minutes . 'm' . $second . 's';
//			} else {
//				$result['avg_time_long'] = $avg_time;
//			}
//			unset($avg_time, $hours, $minutes, $second);
            $result['avg_time_long'] = $avg_time;
		} else {
			$result['avg_time_long'] = 0;
		}
		unset($arr);

		// 领单到给出结果的平均H长
		$arr = $this->selectRaw("count(visa_remark.id) as countnum,sum(visa_remark_attach.customer_handle_time) as alltime")//customer_handle_time
		->leftJoin('visa_remark_attach','visa_remark.id','=','visa_remark_attach.remark_id')
			->where(function ($query) use ($where) {
				foreach ($where as $key => $value) {
					if (!empty($value)) {
						if ($this->symbol[$key] == 'like') {
							$query->where($this->table.'.'.$key, $this->symbol[$key], '%' . $value . '%');
						} elseif ($key == 'start_time') {
							$query->where($this->table.'.'.'create_time', '>=', $value);
						} elseif ($key == 'end_time') {
							$query->where($this->table.'.'."create_time", '<=', $value);
						}elseif ($key == 'visa_time') {
							$query->where("visa_remark.visa_time", '<=', $value);
						} else {
							$query->where($this->table.'.'.$key, $this->symbol[$key], $value);
						}
					}
				}
			})->get()->toArray();
		if (!empty($arr[0]['alltime'] && !empty($arr[0]['countnum']))) {
			$avg_time = intval($arr[0]['alltime'] / $arr[0]['countnum']);
//			$hours = 0;
//			$minutes = 0;
//			$second = 0;
//			$result['p_avg_time_long'] = ''; // 用户排队平均H长
//			if ($avg_time >= 3600) {
//				$hours = intval($avg_time / 3600);
//				$minutes = intval(($avg_time % 3600) / 60);
//				$second = intval(($avg_time % 3600) % 60);
//				$result['p_avg_time_long'] = $hours . 'h' . $minutes . 'm' . $second . 's';
//
//			} elseif ($avg_time > 60) {
//				$minutes = intval(($avg_time % 3600) / 60);
//				$second = intval(($avg_time % 3600) % 60);
//				$result['p_avg_time_long'] = $minutes . 'm' . $second . 's';
//			} else {
//				$result['p_avg_time_long'] = $avg_time;
//			}
//			unset($avg_time, $hours, $minutes, $second);
            $result['p_avg_time_long'] = $avg_time;
		} else {
			$result['p_avg_time_long'] = 0;
		}

		// 拨叫到结束面签H长
		$arr = $this->selectRaw("count(visa_remark.id) as countnum,sum(visa_remark_attach.vedio_handle_time) as alltime")//vedio_handle_time
		->leftJoin('visa_remark_attach','visa_remark.id','=','visa_remark_attach.remark_id')
			->where(function ($query) use ($where) {
				foreach ($where as $key => $value) {
					if (!empty($value)) {
						if ($this->symbol[$key] == 'like') {
							$query->where($this->table.'.'.$key, $this->symbol[$key], '%' . $value . '%');
						} elseif ($key == 'start_time') {
							$query->where($this->table.'.'.'create_time', '>=', $value);
						} elseif ($key == 'end_time') {
							$query->where($this->table.'.'."create_time", '<=', $value);
						}elseif ($key == 'visa_time') {
							$query->where("visa_remark.visa_time", '<=', $value);
						} else {
							$query->where($this->table.'.'.$key, $this->symbol[$key], $value);
						}
					}
				}
			})->get()->toArray();
		if (!empty($arr[0]['alltime'] && !empty($arr[0]['countnum']))) {
			$avg_time = intval($arr[0]['alltime'] / $arr[0]['countnum']);
//			$hours = 0;
//			$minutes = 0;
//			$second = 0;
//			$result['video_avg_time'] = ''; // 用户排队平均H长
//			if ($avg_time >= 3600) {
//				$hours = intval($avg_time / 3600);
//				$minutes = intval(($avg_time % 3600) / 60);
//				$second = intval(($avg_time % 3600) % 60);
//				$result['video_avg_time'] = $hours . 'h' . $minutes . 'm' . $second . 's';
//
//			} elseif ($avg_time > 60) {
//				$minutes = intval(($avg_time % 3600) / 60);
//				$second = intval(($avg_time % 3600) % 60);
//				$result['video_avg_time'] = $minutes . 'm' . $second . 's';
//			} else {
//				$result['video_avg_time'] = $avg_time;
//			}
//			unset($avg_time, $hours, $minutes, $second);
            $result['video_avg_time'] = $avg_time;
		} else {
			$result['video_avg_time'] = 0;
		}

		return $result;
	}

	//获取top 5的
	/**
	 * [getTopNum description]
	 * @param  array   $where [description]
	 * @param  integer $limit [description]
	 * @return [type]         [description]
	 */
	public function getTopNum($where = [], $limit = 5) {

		$res = $this->selectRaw('count(visa_remark.id) as count,'.$this->table.'.seat_id')
			->rightJoin('visa_remark_attach','visa_remark.id','=','visa_remark_attach.remark_id')
			->where(function ($query) use ($where) {
				foreach ($where as $key => $value) {
					if (!empty($value)) {
						if ($this->symbol[$key] == 'like') {
							$query->where($this->table.'.'.$key, $this->symbol[$key], '%' . $value . '%');
						} elseif ($key == 'create_time') {
							$query->where('visa_remark_attach.queuing_time', '>=', $value);
						} elseif ($key == 'end_time') {
							$query->where("visa_remark_attach.create_time", '<=', $value);
						} elseif ($key == 'visa_time') {
							$query->where("visa_remark_attach.visa_time", '<=', $value);
						}elseif ($key == 'status') {
							$query->whereIn($this->table.'.'.$key, $value);
						} else {
							$query->where($this->table.'.'.$key, $this->symbol[$key], $value);
						}
					}
				}
			})->orderBy('count', 'desc')->groupBy($this->table.'.'.'seat_id')->limit($limit)
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
}
 ?>