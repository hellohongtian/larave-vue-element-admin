<?php
namespace App\Http\Controllers\SeatManage;

use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Models\VideoVisa\Role;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\SeatManagerLog;
use App\Models\VideoVisa\VideoVisaModel;
use App\Models\VideoVisa\VisaPool;
use App\Models\VideoVisa\VisaRemark;
use App\Repositories\UserRepository;
use App\Repositories\VisaRemarkRepository;
use App\User;
use Illuminate\Http\Request;
use Mockery\Exception;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\FastVisa;
use App\Repositories\FastVisaRepository;
use App\Repositories\CityRepository;

/**
 * 座席数据分析统计
 */
class SeatAnalysisController extends BaseController
{
    public $visaModel = null;
    public $manageLog = null;
    public $visaPoolModel = null;
    public $fastVisaLog = null;

    public function __construct()
    {
        //$this->visaModel = new VisaRemark();
        $this->visaModel = new fastVisa();

        $this->manageLog = new SeatManagerLog();
        $this->visaPoolModel = new VisaPool();
        $this->fastVisaLog = new FastVisaLog();

    }

    public function getTotalDataMap($seat_id = 0)
    {
        $where = [];
        $where['status_all'] = [5, 6, 7, 8];
        if ($seat_id > 0) {
            $where['seat_id'] = $seat_id;
        }
        $where['created_at'] = strtotime(date('Y-m-d', time()));
        $visamap = $this->visaModel->getVisaMap($where);
        $total_status = [];
        $total_status['audit_num'] = 0;
        foreach ($visamap['data'] as $k => $v) {
            if (in_array($v['status'], [5, 6, 7])) {
                $total_status['audit_num'] += $v['count'];
            }
            switch ($v['status']) {
                case '5':
                    $total_status['audit_pass'] = $v['count'];
                    break;
                case '6':
                    $total_status['audit_reject'] = $v['count'];
                    break;
                case '7':
                    $total_status['visa_jump'] = $v['count'];
                    break;
                case '8':
                    $total_status['queue_again'] = $v['count'];
                    break;
                default:
                    # code...
                    break;
            }
        }
        return $total_status;
    }

// 1未排队 2待处理(已排队未领取) 3处理中(已领取未视频) 4视频中 5审核通过 6审核拒绝 7跳过面签 8重新排队
    public function index(Request $request)
    {
        $params = $request->all();

        $start_date = !empty($params['start_time']) ? $params['start_time'] : date('Y-m-d');
        $end_date = !empty($params['end_time']) ? $params['end_time'] : date('Y-m-d');
//        $end_date = isset($request['end_time']) ? $params['end_time'].' 23:59:59' : date('Y-m-d');
        $staticsData = $this->getStaticsData($start_date, $end_date);
        return view('seat_manage.analysis', [
            'time_statics' => $staticsData['time_statics'],
            'status_count' => $staticsData['status_count'],
            'seat_status' => $staticsData['seat_avg_status_time_statics'],
            'params' => $params,
        ]);
    }

    /**
     * 获取统计数据
     * @param $startDate
     * @param $endDate
     * @param int $seatId
     * @return array
     */
    private function getStaticsData($startDate, $endDate, $seatId = 0)
    {
        $result = [];

        $visaRepository = new FastVisaRepository();

        //饼图统计
        $statusArr = [
            FastVisa::VISA_STATUS_AGREE,
            FastVisa::VISA_STATUS_REFUSE,
            FastVisa::VISA_STATUS_SKIP,
            FastVisa::VISA_STATUS_IN_QUEUE_AND_SEAT,
            FastVisa::VISA_STATUS_HANG
        ];
        //统计各种审核结果的数量
        $result['status_count'] = (new FastVisaRepository())->getVisaCount($statusArr, $startDate, $endDate, $seatId);

        //获取处理面签单的各种时长
        $result['time_statics'] = $visaRepository->getVisaHandleTimeStatics(['start_time'=>strtotime($startDate), 'end_time'=>strtotime($endDate.'+1 day'), 'seat_id'=>$seatId]);

        //获取坐席各个状态时长
        $result['seat_avg_status_time_statics'] = (new SeatManagerLog())->getAvgStatusTime($startDate, $endDate, $seatId);

        return $result;
    }

    // 通过坐席名称进行搜索  获取相关数据
    public function searchByName(Request $request)
    {
        $params = $request->all();
        $seatName = trim($params['seat_name']);

        $startDate = !empty($params['start_time']) ? $params['start_time'] : date('Y-m-d');
        $endDate = !empty($params['end_time']) ? $params['end_time'] : date('Y-m-d');


        if (empty($seatName)) {
            return json_encode(['code' => -1, 'msg' => '座席名称不能为空']);
        }

        //获取seat_id
        $seatId = 0;
        if (UserRepository::isSeat()) {
            if ($seatName != session('uinfo.fullname')) {
                return json_encode(['code' => -1, 'msg' => '您为座席用户，只能查看自己的数据']);
            } else {
                $seatId = session('uinfo.seat_id');
            }
        }
        if (empty($seatId)) {
            $seat = (new SeatManage)->getOne(['id'], ['fullname'=>$seatName,'flag' => Role::FLAG_FOR_RISK]);
            if ($seat) {
                $seatId = $seat['id'];
            } else {
                return json_encode(['code' => -1, 'msg' => '座席不存在']);
            }
        }

        //获取统计数据
        $staticsData = $this->getStaticsData($startDate, $endDate, $seatId);

        $res = [
            'time_statics' => $staticsData['time_statics'],
            'status_count' => $staticsData['status_count'],
            'seat_status' => $staticsData['seat_avg_status_time_statics'],
        ];

        return json_encode(['code' => 1, 'msg' => 'success', 'data' => $res]);
    }

    //获取坐席指定状态时长
    public function getSeatManageTime($where = [])
    {
        $res = $this->manageLog->getAllTimeLong($where);

        //按天数平均
        $start = $where['op_date_range'][0];
        $end = $where['op_date_range'][1];
        $datetime_start = new \DateTime($start);
        $datetime_end = new \DateTime($end);
        $days = $datetime_start->diff($datetime_end)->days + 1;
        if ($days > 0 && $res > 0) {
            $res = gmdate("H:i:s", $res / $days);
        } else {
            $res = 0;
        }

        return $res;
    }
}

?>