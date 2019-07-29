<?php
namespace App\Models\VideoVisa;
use App\Fast\FastKey;
use App\Library\Common;
use App\Library\RedisCommon;
use App\Library\RedisObj;
use App\Models\VideoVisa\NetEase\FastVideoData;
use Illuminate\Support\Facades\DB;
use PDO;
/**
 * 面签列表
 */
class FastVisa extends VideoVisaModel
{
    protected $table = 'fast_visa';
    public $timestamps = false;

    //1未排队 2排队中 3处理中(已领取未视频) 4视频中 5审核通过 6审核拒绝
    // 7跳过面签 8重新排队（已指定坐席） 9可派发可领取 10挂起 11挂起排队
    const VISA_STATUS_NOT_IN_QUEUE = 1;
    const VISA_STATUS_IN_QUEUEING = 2;
    const VISA_STATUS_IN_SEAT = 3;
    const VISA_STATUS_IN_VIDEO = 4;
    const VISA_STATUS_AGREE = 5;
    const VISA_STATUS_REFUSE = 6;
    const VISA_STATUS_SKIP = 7;
    const VISA_STATUS_IN_QUEUE_AND_SEAT = 8;
    const VISA_STATUS_CAN_DISPATCH_AND_PICK = 9;
    const VISA_STATUS_HANG = 10;
    const VISA_STATUS_HANG_QUEUEING = 11;

    //通知坐席的消息类型 1新订单 2挂起订单
    const NOTIFY_SEAT_NEW_VISA = 1;
    const NOTIFY_SEAT_HANG_VISA_BACK = 2;

    //erp信审状态
    const VISA_ERP_CRESIT_STATUS_MAN = 1;
    const VISA_ERP_CRESIT_STATUS_AUTO = 2;

    //拒绝标记
    const VISA_REFUSE_CATEGORY_D_DG = 1;#代购
    const VISA_REFUSE_CATEGORY_D_FD = 2;#反贷
    const VISA_REFUSE_CATEGORY_D_XJ = 3;#虚假信息
    const VISA_REFUSE_CATEGORY_D_QY = 4;#区域要求
    const VISA_REFUSE_CATEGORY_D_GW = 5;#高危
    const VISA_REFUSE_CATEGORY_D_OT = 6;#其他
    const VISA_REFUSE_CATEGORY_D_DF = 7;#多方借贷
    const VISA_REFUSE_CATEGORY_D_QZ = 8;#欺诈
    const VISA_REFUSE_CATEGORY_D_YT = 9;#代替别人购买
    const VISA_REFUSE_CATEGORY_D_OT1 = 10;#其他1
    const VISA_REFUSE_CATEGORY_D_OT2 = 11;#其它2
    const VISA_REFUSE_CATEGORY_D_OT3 = 12;#其它3
    const VISA_REFUSE_CATEGORY_D_OT4 = 13;#其它4
    const VISA_REFUSE_CATEGORY_D_XJ2 = 14;#XJ2
    const VISA_REFUSE_CATEGORY_D_XT = 15;#XT
    const VISA_REFUSE_CATEGORY_D_NL = 16;
    const VISA_REFUSE_CATEGORY_D_QT = 17;
    const VISA_REFUSE_CATEGORY_D_ID2 = 18;
    const VISA_REFUSE_CATEGORY_D_HY = 19;
    const VISA_REFUSE_CATEGORY_D_GX = 20;

    //复议状态
    const VISA_RECONSIDERATION_STATUS_NOT = 1;
    const VISA_RECONSIDERATION_STATUS_CAN = 2;
    const VISA_RECONSIDERATION_STATUS_DOING = 3;
    const VISA_RECONSIDERATION_STATUS_PASS = 4;
    const VISA_RECONSIDERATION_STATUS_REFUSE = -1;
    const VISA_RECONSIDERATION_STATUS_OVERRULE = -2;


    //复议状态中文对照
    public static $visa_reconsideration_map = [
        self::VISA_RECONSIDERATION_STATUS_NOT => '可复议',
        self::VISA_RECONSIDERATION_STATUS_CAN => '已发起复议',
        self::VISA_RECONSIDERATION_STATUS_DOING => '复议中',
        self::VISA_RECONSIDERATION_STATUS_PASS => '复议通过',
        self::VISA_RECONSIDERATION_STATUS_REFUSE => '复议拒绝',
        self::VISA_RECONSIDERATION_STATUS_OVERRULE => '复议驳回'

    ];
    //拒绝标记
    public static $visa_refuse_category_old = [
         self::VISA_REFUSE_CATEGORY_D_DG => '代购(D-DG)',
         self::VISA_REFUSE_CATEGORY_D_FD => '反贷(D-FD)',
         self::VISA_REFUSE_CATEGORY_D_XJ => '虚假信息(D-XJ)',
         self::VISA_REFUSE_CATEGORY_D_QY => '区域要求(D-QY)',
         self::VISA_REFUSE_CATEGORY_D_GW => '高危(D-GW)',
         self::VISA_REFUSE_CATEGORY_D_OT => '其他',
    ];
    //拒绝标记
    public static $visa_refuse_category = [
        self::VISA_REFUSE_CATEGORY_D_YT => 'D-YT',
        self::VISA_REFUSE_CATEGORY_D_DG => 'D-DG',//已弃用 ,又启用
        self::VISA_REFUSE_CATEGORY_D_FD => 'D-FD',
        self::VISA_REFUSE_CATEGORY_D_DF => 'D-DF',
        self::VISA_REFUSE_CATEGORY_D_XJ => 'D-XJ',
        self::VISA_REFUSE_CATEGORY_D_XJ2 => 'D-XJ2',
        self::VISA_REFUSE_CATEGORY_D_ID2 => 'D-ID2',
        self::VISA_REFUSE_CATEGORY_D_GW => 'D-GW',
        self::VISA_REFUSE_CATEGORY_D_HY => 'D-HY',
        self::VISA_REFUSE_CATEGORY_D_GX => 'D-GX',
        self::VISA_REFUSE_CATEGORY_D_NL => 'D-NL',
        self::VISA_REFUSE_CATEGORY_D_XT => 'D-XT',
        self::VISA_REFUSE_CATEGORY_D_QT => 'D-QT',
        self::VISA_REFUSE_CATEGORY_D_QY => 'D-QY',
        self::VISA_REFUSE_CATEGORY_D_QZ => 'D-QZ',
        self::VISA_REFUSE_CATEGORY_D_OT => '其他',//已弃用
        self::VISA_REFUSE_CATEGORY_D_OT1 => '其他1',
        self::VISA_REFUSE_CATEGORY_D_OT2 => '其他2',
        self::VISA_REFUSE_CATEGORY_D_OT3 => '其他3',
        self::VISA_REFUSE_CATEGORY_D_OT4 => '其他4',
    ];

    //状态中文对照
    public static $visaStatusChineseMap = [
        self::VISA_STATUS_NOT_IN_QUEUE => '未排队',
        self::VISA_STATUS_IN_QUEUEING => '排队中',
        self::VISA_STATUS_IN_SEAT => '处理中',
        self::VISA_STATUS_IN_VIDEO => '视频中',
        self::VISA_STATUS_AGREE => '审核通过',
        self::VISA_STATUS_REFUSE => '审核拒绝',
        self::VISA_STATUS_SKIP => '跳过面签',
        self::VISA_STATUS_IN_QUEUE_AND_SEAT => '重新排队',
        self::VISA_STATUS_CAN_DISPATCH_AND_PICK => '可领取',
        self::VISA_STATUS_HANG => '挂起',
        self::VISA_STATUS_HANG_QUEUEING => '挂起排队',
    ];

    //待审核状态
    public static $waitForVisaStatusList = [
        self::VISA_STATUS_IN_SEAT,
        self::VISA_STATUS_IN_VIDEO,
        self::VISA_STATUS_CAN_DISPATCH_AND_PICK,
        self::VISA_STATUS_HANG_QUEUEING,
    ];
    //erp信审状态
    public static $visaErpStatusChineseMap = [
        self::VISA_ERP_CRESIT_STATUS_MAN => '人工信审',
        self::VISA_ERP_CRESIT_STATUS_AUTO => '机器信审',
    ];
    //可进入视频详情的状态列表
    public static $canJumpToVideoStatusList = [
        self::VISA_STATUS_IN_SEAT,
        self::VISA_STATUS_IN_VIDEO,
        self::VISA_STATUS_CAN_DISPATCH_AND_PICK,
        self::VISA_STATUS_HANG_QUEUEING,
    ];

    //已经有审核结果的状态列表
    public static $visaFinishedStatusList = [
        self::VISA_STATUS_AGREE,
        self::VISA_STATUS_REFUSE,
        self::VISA_STATUS_SKIP,
    ];
    public static $visaFinishedStatusChineseMap = [
        self::VISA_STATUS_AGREE => '审核通过',
        self::VISA_STATUS_REFUSE => '审核拒绝',
        self::VISA_STATUS_SKIP => '跳过面签',
    ];
    //有排队的或被处理的状态(新)
    public static $inQueueOrDealStatusList = [
        self::VISA_STATUS_IN_SEAT,
        self::VISA_STATUS_IN_VIDEO,
        self::VISA_STATUS_HANG_QUEUEING,
        self::VISA_STATUS_IN_QUEUEING,
        self::VISA_STATUS_CAN_DISPATCH_AND_PICK,
    ];
    //可以分配坐席的订单状态
    public static $canAssignStatusList = [
        self::VISA_STATUS_NOT_IN_QUEUE, #1
        self::VISA_STATUS_IN_QUEUEING,#2
        self::VISA_STATUS_IN_QUEUE_AND_SEAT,#8
        self::VISA_STATUS_CAN_DISPATCH_AND_PICK,#9
        self::VISA_STATUS_HANG,#10
    ];
    public $symbol = [
        'status' => '=',
        'carid' => '=',
        'channel' => '=',
        'applyid' => '=',
        'fullname' => 'like',
        'car_name' => 'like',
        'business_type' => '=',
        'status_all' => 'in',
        'created_at' => '>=',
        'end_time' => '<=',
        'seat_id' => '=',
        'visa_time' => '>=',
    ];
    
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
                        $query->where('created_at', '>=', $value);
                    } elseif ($key == 'end_time') {
                        $query->where("created_at", '<=', $value);
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
    public function getVisaInfoById($visaId)
    {
        return $this->getOne(['*'], ['id'=>$visaId]);
    }

    public function getVisaInfoByMasterId($masterId)
    {
        return $this->getOne(['*'], ['master_id'=>$masterId]);
    }

    /**
     * 更新订单状态
     * 项目中所有涉及visa更新状态的都必须走这个函数
     * @param $status
     * @param $where
     * @param array $extraFields
     * @return mixed
     */
    public function updateVisaStatus($status, $where, $extraFields = [])
    {
        $updateData['status'] = $status;

        if ($extraFields) {
            foreach ($extraFields as $fieldName => $value) {
                $updateData[$fieldName] = $value;
            }
        }
        return $this->updateBy($updateData, $where);
    }

    /**
     * 项目中所有不涉及状态变更的更新才做都走这个函数
     */
    public function updateVisa($updateData, $where)
    {
        return $this->updateBy($updateData, $where);
    }

    /**
     * 是否经过视频
     * todo tanrenzong 这个是暂时的方案，上线前是否有更合理的
     * @param $visaId
     * @return bool
     */
    public function isAlreadyVideo($visaId)
    {
        $result = false;

        $existChannelId = (new FastVisaResult())->getOne(['channel_id'], ['visa_id'=>$visaId, 'channel_id !=' => ''], ['id'=>'desc']);
        if ($existChannelId) {
            $result = true;
        }
//        $video = (new FastVideoData())->getOne(['channel_id'], ['visa_id'=>$visaId]);
//        if ($video && isset($video['channel_id']) && !empty($video['channel_id'])) {
//            $result = true;
//        }

        return $result;
    }

    /**
     * 是否visa已被加锁
     * @param $visaId
     * @return bool
     */
    public static function isVisaLocked($visaId)
    {
        return (bool)RedisObj::instance()->get(self::getLockKey($visaId));
    }

    /**
     * 给visa加锁
     * @param $visaId
     */
    public static function lockVisa($visaId)
    {
//        RedisObj::instance()->setex(self::getLockKey($visaId), $visaId, 5);
        return Common::redis_lock(self::getLockKey($visaId));
    }

    /**
     * 给visa解锁
     * @param $visaId
     */
    public static function unLockVisa($visaId)
    {
//        RedisObj::instance()->delete(self::getLockKey($visaId));
        return Common::redis_lock(self::getLockKey($visaId),true);
    }

    /**
     * 获取visa锁的redis key
     * @param $visaId
     * @return string
     */
    public static function getLockKey($visaId)
    {
        return FastKey::VISA_LOCK . $visaId;
    }

    /**
     * @note 获取统计数据
     * @param $start_time
     * @param $end_time
     */
    public function get_analysis_data($start_time,$end_time){

        $return = [];
        $dt_start = strtotime($start_time);
        $dt_end = strtotime('+1 day',strtotime($end_time));
        $init_arr = [
            'order' => 0,//订单
            'auto_credit' => 0,//机器信审
            'men_credit' => 0,//人工信审
            'local_type' => 0,//本地购
            'im_type' => 0,//im
            'x1_type' => 0,//x1
            'x2_type' => 0,//x2
            'x3_type' => 0,//x3
        ];
        #format返回
        while ($dt_start<$dt_end){
            $return[date('Y-m-d',$dt_start)] =$init_arr;
            $dt_start = strtotime('+1 day',$dt_start);
        }
        #查询每天总订单数
        DB::setFetchMode(PDO::FETCH_ASSOC);
        $order_res = DB::table('fast_visa')
            ->select(DB::raw("count(*) as count ,FROM_UNIXTIME(line_up_time,'%Y-%m-%d') as day_time"))
            ->where('line_up_time', '>=', strtotime($start_time))
            ->where('line_up_time', '<', $dt_end)
            ->whereIn('status',[5,6,7])
            ->groupBy('day_time')
            ->get()
            ->toArray();
        foreach ($order_res as $k=>$v) {
            $day_time = $v['day_time'];
            if(isset($return[$day_time])){
                $return[$day_time]['order'] = $v['count'];
            }
        }
        #查询信审类型
        $erp_credit_status_res = DB::table('fast_visa')
            ->select(DB::raw("count(*) as count,erp_credit_status,FROM_UNIXTIME(line_up_time,'%Y-%m-%d') as day_time"))
            ->where('line_up_time', '>=', strtotime($start_time))
            ->where('line_up_time', '<', $dt_end)
            ->whereIn('status',[5,6,7])
            ->groupBy('day_time')
            ->groupBy('erp_credit_status')
            ->get()
            ->toArray();
        foreach ($erp_credit_status_res as $k=>$v) {
            $day_time = $v['day_time'];
            if(isset($v['erp_credit_status']) && $v['erp_credit_status']==1){
                $return[$day_time]['men_credit'] = round($v['count']/$return[$day_time]['order']*100);
            }
            if(isset($v['erp_credit_status']) && $v['erp_credit_status']==2){
                $return[$day_time]['auto_credit'] = round($v['count']/$return[$day_time]['order']*100);
            }
        }
        #查询渠道类型
        $sales_type_res = DB::table('fast_visa')
            ->select(DB::raw("count(*) as count,sales_type,FROM_UNIXTIME(line_up_time,'%Y-%m-%d') as day_time"))
            ->where('line_up_time', '>=', strtotime($start_time))
            ->where('line_up_time', '<', $dt_end)
            ->whereIn('status',[5,6,7])
            ->groupBy('day_time')
            ->groupBy('sales_type')
            ->get()
            ->toArray();
        foreach ($sales_type_res as $k=>$v) {
            $day_time = $v['day_time'];
            if(!empty($v['sales_type'])){
                $sales_type = $v['sales_type'];
                switch ($sales_type){
                    case 1:
                        $return[$day_time]['x1_type'] = round($v['count']/$return[$day_time]['order']*100);
                        break;
                    case 2:
                        $return[$day_time]['x2_type'] = round($v['count']/$return[$day_time]['order']*100);
                        break;
                    case 3:
                        $return[$day_time]['x3_type'] = round($v['count']/$return[$day_time]['order']*100);
                        break;
                    case 20:
                        $return[$day_time]['local_type'] = round($v['count']/$return[$day_time]['order']*100);
                        break;
                    case 21:
                        $return[$day_time]['im_type'] = round($v['count']/$return[$day_time]['order']*100);
                        break;
                }
            }
        }
        $res = [
            'order' => array_column($return,'order'),//订单
            'auto_credit' => array_column($return,'auto_credit'),//机器信审
            'men_credit' => array_column($return,'men_credit'),//人工信审
            'local_type' => array_column($return,'local_type'),//本地购
            'im_type' => array_column($return,'im_type'),//im
            'x1_type' => array_column($return,'x1_type'),//x1
            'x2_type' => array_column($return,'x2_type'),//x2
            'x3_type' => array_column($return,'x3_type'),//x3
            'day_time' => array_map(function($value){
                $day = explode('-',$value);
                return ltrim($day[1],0)."月".ltrim($day[2],0).'日';
            },array_keys($return)),//日期
        ];
        return $res;
//        dd($res);
    }
}