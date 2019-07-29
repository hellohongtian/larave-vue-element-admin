<?php
namespace App\Http\Controllers\Video;
use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\NetEase\FastVideoCallBack;
use App\Models\VideoVisa\NetEase\FastVideoData;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\VideoVisaModel;
use App\Models\VideoVisa\VisaRemark;
use App\Repositories\Visa\VisaRepository;
use Illuminate\Http\Request;
use App\Models\VideoVisa\ReceiveInfo;

/**
 * 视频回放控制器
 */
class VideoController extends BaseController {
    public $videoModel = null;
    public $common = null;
    public $videoInfoModel = null;
    public $business_type = [];

    public function __construct() {
        parent::__construct();
        $this->videoModel = new VisaRemark();
        $this->common = new Common();
        $this->videoInfoModel = new ReceiveInfo();
        $tmp = $this->common->getAllProductScheme();
        foreach ($tmp as $key => $value) {
            if (!empty($value)) {
                $this->business_type[$key] = $value;
            }
        }
    }

    public function index(Request $request) {
        $paramsAll = $request->all();
        $params['pagesize'] = isset($request['pagesize']) ? $request['pagesize'] : 5;
        $params['status'] = !empty($paramsAll['status']) ? $paramsAll['status'] : '';
        $params['applyid'] = !empty($paramsAll['applyid']) ? $paramsAll['applyid'] : '';
        $params['fullname'] = !empty($paramsAll['fullname']) ? $paramsAll['fullname'] : '';
        $params['mobile'] = !empty($paramsAll['mobile']) ? $paramsAll['mobile'] : '';
        $params['carid'] = !empty($paramsAll['carid']) ? $paramsAll['carid'] : '';
        $params['car_name'] = !empty($paramsAll['car_name']) ? $paramsAll['car_name'] : '';
        $params['channel'] = !empty($paramsAll['channel']) ? $paramsAll['channel'] : '';
        $params['business_type'] = !empty($paramsAll['business_type']) ? $paramsAll['business_type'] : '';

        $paramList = $this->formatParamList($params);

        $visaRepository = new VisaRepository();

        //有结果的面签
        $visaEndList = $visaRepository->getFinishedVisaList($paramList,$params['pagesize']);

        //坐席id和名称的映射
        $seatList = (new SeatManage())->getSeatIdToFullNameArr();

        $viewData = [
            'list' => $visaEndList,
            'request' => $params,
            'capital_channel' => $visaRepository->capital_channel,
            'business_type' => $this->business_type,
            'seat_list' => $seatList,
        ];

        return view('video.index', $viewData);
    }


    private function formatVideoCallBack($fileInfo) {
        if (!empty($data['caller'])) {
            if ($data['caller']) {
                $data['caller'] = 1;
            }else{
                $data['caller'] = 0;
            }
        }

        if (!empty($data['mix'])) {
            if ($data['mix']) {
                $data['mix'] = 1;
            }else{
                $data['mix'] = 0;
            }
        }
        $data['eventType'] = 6;
        $data['sources'] = json_encode($data);
        $channelid = $data['channelid'];
    }

    private function formatParamList($request)
    {
        //接收参数
        $status = isset($request['status']) ? $request['status'] : 0;
        $applyid = isset($request['applyid']) ? $request['applyid'] : 0;
        $fullname = isset($request['fullname']) ? $request['fullname'] : '';
        $mobile = isset($request['mobile']) ? $request['mobile'] : '';
        $carid = isset($request['carid']) ? $request['carid'] : '';
        $channel = isset($request['channel']) ? $request['channel'] : 0;
        $business_type = isset($request['business_type']) ? $request['business_type'] : '';

        $risk_start_name = isset($request['risk_start_name']) ? $request['risk_start_name'] : '';
        $car_cityid = isset($request['car_cityid']) ? $request['car_cityid'] : '';
        $risk_at = isset($request['risk_at']) ? $request['risk_at'] : '';

        $params = [];
        if ($status) $params['status'] = $status;
        if ($applyid) $params['apply_id'] = $applyid;
        if ($fullname) $params['full_name'] = $fullname;
        if ($mobile) $params['mobile'] = $mobile;
        if ($carid) $params['car_id'] = $carid;
        if ($channel) $params['channel'] = $channel;
        if ($business_type) $params['business_type'] = $business_type;
        if ($risk_start_name) $params['risk_start_name'] = $risk_start_name;
        if ($car_cityid) $params['car_city_id'] = $car_cityid;
        if ($risk_at) $params['risk_time'] = $risk_at;
        return $params;
    }
}