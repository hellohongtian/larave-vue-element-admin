<?php
/**
 * 坐席管理
 * User: wood
 * Date: 2017/10/16
 */
namespace App\Repositories;

use App\Library\Helper;
use App\Library\RedisCommon;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\SeatManagerLog;
use App\Models\VideoVisa\ImAccount;
use Illuminate\Support\Facades\DB;
use App\Library\Common;
use Illuminate\Support\Facades\Event;
use App\Models\VideoVisa\FastVisaLog;


class SeatManageRepository
{
    public $seat_model = null;
    public $seat_model_log = null;
    public $fast_visa_result = null;

    //异常数据邮件
    protected $api_error_email;


    function __construct()
    {
        $this->api_error_email =  config('mail.developer');
        $this->seat_model = new SeatManage();
        $this->seat_model_log = new SeatManagerLog();
        $this->fast_visa_result = new FastVisaResult();
    }

    /**
     * 根据条件获取坐席列表-分页
     * @param array $fields
     * @param array $params
     * @param int $pageSize
     * @return mixed
     */
    public function getListByCondition($fields = ['*'], $params = [], $pageSize = 5){
        $ret = $this->seat_model->getList($fields, $params, [], [], $pageSize);
        return $ret;
    }

    /**
     * 根据条件获取坐席列表-全部
     * @param array $fields
     * @param array $params
     * @return mixed
     */
    public function getAllByCondition($fields = ['*'], $params = []){
        $where = [];
        $ret = $this->seat_model->getAll($fields, $where);
        return $ret;
    }

    //获取单条记录
    public function getOneInfoById($id, $fields = ['*']){
        if(!$id)
            return [];
        return $this->seat_model->getOne($fields, ['id'=>$id]);
    }

    //获取单条网易账号信息
    public function getImAccountInfo($account_id, $fields = ['*']){
        $im_account = new ImAccount();
        return $im_account->getOne($fields, ['id'=>$account_id]);
    }

    //更新网易账号信息
    public function saveImData($data, $id){
        $im_account = new ImAccount();
        return $im_account->updateBy($data, ['id'=>$id]);
    }

    //添加坐席
    public function addData($data){
        return $this->seat_model->insert($data);
    }

    //添加网易账号信息
    public function addImAccount($data){
        $im_account = new ImAccount();
        return $im_account->insertGetId($data);
    }

    //检测坐席是否存在
    public function checkSeat($where){
        if(!$where){
            return true;
        }
        $seat_info = $this->seat_model->getOne(['id'],$where);
        return $seat_info ? true : false;
    }


    //根据masterid获取坐席信息
    public function getSeatInfoByMasterId($fields=['*'], $masterid){

        if(!$masterid){
            return [];
        }

        $info = $this->seat_model->getOne($fields, ['id'=>$masterid]);
        return $info;
    }

    //修改坐席状态
    public function editStatus($id, $status = 0){
        if(!$id || !in_array($status, [1,2])){
            return false;
        }
        return $this->seat_model->updateBy(['status'=>$status],['id'=>$id]);
    }

    //记录坐席时间log
    public function upSeatStatusLog($seat_id,$now_status){
        $result = '';
        $query = $this->seat_model_log->select('id', 'type', 'start_time')
            ->where('seat_id',$seat_id)
            ->where('end_time',0)
            ->orderBy('id','desc')
            ->first();
        $time = time();
        if(!empty($query)){
            $res = $query->toArray();
            if(!empty($res)  && $res['type'] == $now_status){
                return false;
            }
            $update = [
                'end_time'=>$time,
                'duration_time'=>$time-$res['start_time']
            ];
            //修改旧状态结束时间
            $result = $this->seat_model_log->where('id',$res['id'])->update($update);
        }
        //添加一条新的log记录，记录最新的状态,退出不需要添加记录
        if($now_status != 4){
            $data =[
                'seat_id'=>$seat_id,
                'type'=>$now_status,
                'start_time'=>$time,
                'op_date'=>date('Y-m-d',$time),
                'create_time'=>$time,
            ];
            $result = $this->seat_model_log->insert($data);
        }
        return $result;
    }

    /**
     * 首页图表数据当日每个整点排队用户及客服面签处理数量
     */
    public function getHomeChartsData() {
        $chartData = [
            'queuing_data' => [],//排队
            'visa_data' => [],//处理
            'clock' => []//时间
        ];
        $time = strtotime(date("Y-m-d"));

        $res = (new FastVisaLog())
            ->select(DB::raw("count(distinct visa_id) as queuing_num,from_unixtime(`queuing_time`,'%H:00') as t"))
            ->where('queuing_time', '>=', $time)
            ->where('visa_time', '=', 0)
            ->groupBy('t')
            ->get()
            ->toArray();
        $res2 = (new FastVisaLog())
            ->select(DB::raw("count(visa_id) as visa_num,from_unixtime(`visa_time`,'%H:00') as t"))
            ->where('visa_time', '>=', $time)
            ->whereIn('visa_status', FastVisa::$visaFinishedStatusList)
            ->groupBy('t')
            ->get()
            ->toArray();
//        $res = [
//            [
//                'queuing_num' => 1,
//                't' => '2019-01-09 08'
//            ],
//            [
//                'queuing_num' => 22,
//                't' => '2019-01-09 09'
//            ],
//            [
//                'queuing_num' => 36,
//                't' => '2019-01-09 10'
//            ],
//            [
//                'queuing_num' => 56,
//                't' => '2019-01-09 11'
//            ],
//            [
//                'queuing_num' => 68,
//                't' => '2019-01-09 12'
//            ],
//            [
//                'queuing_num' => 73,
//                't' => '2019-01-09 13'
//            ],
//            [
//                'queuing_num' => 99,
//                't' => '2019-01-09 14'
//            ],
//
//            [
//                'queuing_num' => 93,
//                't' => '2019-01-09 15'
//            ],
//            [
//                'queuing_num' => 36,
//                't' => '2019-01-09 16'
//            ],
//        ];
//        $res2 = [
//
//            [
//                'visa_num' => 6,
//                't' => '2019-01-09 09'
//            ],
//            [
//                'visa_num' => 14,
//                't' => '2019-01-09 10'
//            ],
//            [
//                'visa_num' => 29,
//                't' => '2019-01-09 11'
//            ],
//            [
//                'visa_num' => 26,
//                't' => '2019-01-09 12'
//            ],
//            [
//                'visa_num' => 29,
//                't' => '2019-01-09 13'
//            ],
//            [
//                'visa_num' => 45,
//                't' => '2019-01-09 14'
//            ],
//
//            [
//                'visa_num' => 43,
//                't' => '2019-01-09 15'
//            ],
//            [
//                'visa_num' => 32,
//                't' => '2019-01-09 16'
//            ],
//        ];
//        dd($res,$res2);
        $data = array_merge($res,$res2);
        if(!empty($data)){
            $data = array_reduce($data ,function(&$newData,$v){
                $clock = $v['t'];
                if(isset($v['queuing_num'])){
                    $newData[$clock]['queuing_num'] = isset($v['queuing_num']) ? $v['queuing_num']: 0;
                }else{
                    $newData[$clock]['queuing_num'] = !empty($newData[$clock]['queuing_num'])? $newData[$clock]['queuing_num']:0;
                }
                if(isset($v['visa_num']) ){
                    $newData[$clock]['visa_num'] = isset($v['visa_num']) ? $v['visa_num']: 0;
                }else{
                    $newData[$clock]['visa_num'] = !empty($newData[$clock]['visa_num'])? $newData[$clock]['visa_num']:0;
                }
                return $newData;
            });
            ksort($data);
            $chartData['queuing_data'] = array_column($data, 'queuing_num');
            $chartData['visa_data'] = array_column($data, 'visa_num');
            $chartData['clock']  = array_keys($data);
        }
        return $chartData;
    }


    /**
     * @note 获取坐席当前处理情况 本月人均处理 100 单    本月您已处理 120 单    您当前挂起 9 单
     * @param int $seat_id
     * @return array
     */
    public function get_dealed_with_order($seat_id)
    {
        $res_arr = ['avg_deal' => 0,'dealed' => 0, 'hang' => 0 ,'need_to_do' => 0];
        $date = date('Y-m-1 00:00:00',time());

        if(empty($seat_id) || !is_numeric($seat_id)){
            return $res_arr;
        }
        #人均处理
        $avg_deal_total_order = $this->fast_visa_result->countByNew([
            'created_at >' =>$date,
            'in' => [
                'visa_status' => [
                    FastVisaResult::VISA_RESULT_STATUS_AGREE,
                    FastVisaResult::VISA_RESULT_STATUS_REFUSE,
                    FastVisaResult::VISA_RESULT_STATUS_SKIP,
                ]
            ],
        ]);
        $avg_deal_total_seat = $this->fast_visa_result->countByNew([
            'created_at >' => $date,
            'in' => [
                'visa_status' => [
                    FastVisaResult::VISA_RESULT_STATUS_AGREE,
                    FastVisaResult::VISA_RESULT_STATUS_REFUSE,
                    FastVisaResult::VISA_RESULT_STATUS_SKIP,
                ]
            ],
        ],[],['seat_id']);
        $avg_deal = 0;
        if(!empty($avg_deal_total_seat)){
            $avg_deal = round($avg_deal_total_order/$avg_deal_total_seat);
        }
        #已处理
        $dealed = $this->fast_visa_result->countByNew([
            'seat_id' => $seat_id,
            'created_at >' => $date,
            'in' => [
                'visa_status' => [
                    FastVisaResult::VISA_RESULT_STATUS_AGREE,
                    FastVisaResult::VISA_RESULT_STATUS_REFUSE,
                    FastVisaResult::VISA_RESULT_STATUS_SKIP,
                ]
            ]
        ],[],['visa_id']);
        #当前挂起
        $fastVisaModel = new FastVisa();
        $hang = $fastVisaModel->countByNew([
            'seat_id' => $seat_id,
            'line_up_time >=' => strtotime(date('Y-m-d')),
            'in' => [
                'status' => [
                    FastVisa::VISA_STATUS_HANG,
                    FastVisa::VISA_STATUS_HANG_QUEUEING,
                ]
            ]
            ]);
//        #未处理总数
//        $need_to_do = $fastVisaModel->countBy([
//            'in' => [
//                'status' => [
//                    FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK,
//                    FastVisa::VISA_STATUS_HANG_QUEUEING,
//                ]
//            ]
//        ]);
        $res_arr = ['avg_deal' => $avg_deal,'dealed' => $dealed, 'hang' => $hang ];

        return $res_arr;
    }

    /**
     * 获取缓存的seat_id对应的name
     */
    public static function get_cache_user_name($seat_id)
    {
        $redis = new RedisCommon();
        $name_key = config('common.seat_name_key');
        $name_list = $redis->get($name_key);
        if($name_list && isset($name_key[$seat_id])){
            return $name_list[$seat_id]['fullname'];
        }
        $seat_model = new SeatManage();
        $res = $seat_model->getAll(['id','fullname']);
        $common = new Common();
        $res = $common->formatArr($res, 'id');
        $redis->setex($name_key, $res, 3600 * 24 * 1);//城市信息一般不会修改，改为一个月
        return $name_list[$seat_id]['fullname'];

    }
}
