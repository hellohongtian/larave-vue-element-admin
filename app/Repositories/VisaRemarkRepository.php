<?php
namespace App\Repositories;

use App\Models\VideoVisa\VisaRemark;
use App\Models\VideoVisa\VisaRemarkAttach;

class VisaRemarkRepository {
	//视频面签记录
	public $video_remark_model = null;

	public function __construct() {
		$this->video_remark_model = new VisaRemark();
	}

	/**
	 * 根据条件获取面签审列表-分页
	 * @param array $fields
	 * @param array $params
	 * @return mixed
	 */
	public function getList($fields = ['*'], $params = []) {
		$where = [];
		$where['in'] = [];
		if (isset($params['status'])) {
			if (is_array($params['status'])) {
				$status_in = ['status' => $params['status']];
				$where['in'] = array_merge($where['in'], $status_in);
			} else {
				$where['status'] = $params['status'];
			}
		}

		if (isset($params['start_time']) && isset($params['end_time'])) {
			$where['create_time >'] = $params['start_time'];
			$where['create_time <'] = $params['end_time'];
		}

		if (isset($params['applyid'])) {
			$where['applyid'] = $params['applyid'];
		}

		if (isset($params['mobile'])) {
			$where['mobile'] = $params['mobile'];
		}

		if (isset($params['carid'])) {
			$where['carid'] = $params['carid'];
		}

		if (isset($params['channel'])) {
			$where['channel'] = $params['channel'];
		}

		if (isset($params['business_type'])) {
			$where['business_type'] = $params['business_type'];
		}

		if (isset($params['fullname'])) {
			$where['fullname like'] = $params['fullname'];
		}

		if (isset($params['applyid'])) {
			$where['applyid'] = $params['applyid'];
		}

		if (isset($params['seat_id'])) {
			$where['seat_id'] = $params['seat_id'];
		}

        if(isset($params['risk_at'])){
            $where['risk_at'] = $params['risk_at'];
        }
        if(isset($params['car_cityid'])){
            $where['car_cityid'] = $params['car_cityid'];
        }
        if(isset($params['risk_start_name'])){
            $where['risk_start_name'] = $params['risk_start_name'];
        }

		if (!$where['in']) {
			unset($where['in']);
		}
		$ret = $this->video_remark_model->getList($fields, $where, $orderBy = ['id' => 'desc']);
		return $ret;
	}

	//根据订单号查询审核列表
	public function getCheckListByApplyId($applyid) {
		if (!$applyid) {
			return [];
		}
		$feilds = ['applyid','inputted_id','status', 'visa_time', 'seat_id', 'seat_name', 'inside_opinion'];
		$where = [
			'applyid' => $applyid,
			'in' => ['status' => [5, 6, 8]],
		];
		$obj = new VisaRemarkAttach();
		$ret = $obj->getAll($feilds, $where);

		return $ret;
	}

}