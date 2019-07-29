<?php
namespace App\Models\VideoVisa;

/**
 * 面签记录表
 */
class FastVisaLog extends VideoVisaModel
{

    protected $table = 'fast_visa_log';
    public $timestamps = false;

    const MATCH_ORDER_TYPE_MEN = 1;
    const MATCH_ORDER_TYPE_AUTO = 2;
    /**
     * 项目新增visa log唯一入口
     * @param $data
     * @return mixed
     */
    public function insertVisaLog($data)
    {
        return $this->insertGetId($data);
    }

    /**
     * 项目更新visa log唯一入口
     * @param $updateData
     * @param $where
     * @return mixed
     */
    public function updateVisaLog($updateData, $where)
    {
        return $this->updateBy($updateData, $where);
    }

    public $symbol = [
        'applyid' => '=',
        'userid' => '=',
        'mobile' => '=',
        'seat_id' => '=',
        'status' => 'in',
        'queuing_time' => '>=',
        'end_video_time' => '>=',
        'is_hang' => '=',
        'created_at' => '>=',

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