<?php
namespace App\Http\Controllers;
use App\Http\Controllers\BaseController;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\VideoVisaModel;
use App\Models\VideoVisa\VisaRemark;
use App\Models\VideoVisa\VisaRemarkAttach;
use App\Models\VideoVisa\FastVisa;
use App\Repositories\Visa\ActionRepository;
use DB;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use App\Repositories\FastVisaRepository;
use App\Repositories\SeatManageRepository;


/**
 *
 */
class IndexController extends BaseController {
	protected $visaPollModel = null;
	protected $action_rep;
	public $visaModel = null;
	public $visaAttachModel = null;
	public $seat_manager_obj = null;

	public function __construct() {

		//$this->visaPollModel = new VisaPool();
//		$this->visaModel = new VisaRemark();
        $this->visaModel = new fastVisa();
        $this->visaAttachModel = new VisaRemarkAttach();
        $this->seat_manager_obj = new SeatManage();
	}

	public function indexmain(Request $request) {
		// 获取面签用户排队总数
		$dateUnix = strtotime(date('Y-m-d'));
        $seat_id = empty(session('uinfo.seat_id'))? 0:intval(session('uinfo.seat_id'));
        $visaCount = $this->visaModel
            ->where('line_up_time','>',$dateUnix)
            ->whereIn('status', [FastVisa::VISA_STATUS_IN_QUEUEING,FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK])
            ->count();

		//完成面签的人数
        $complate_visaCount = $this->visaModel
            ->where('line_up_time','>',$dateUnix)
            ->whereIn('status', FastVisa::$visaFinishedStatusList)
            ->count();

        $visaRepository = new FastVisaRepository();
        //第五版需求，top更改为在线坐席
//		$top = $visaRepository->getVisaTopUser($dateUnix, 5);
        #当前在线坐席
        $free = $this->seat_manager_obj->getAll(['id','fullname'],
            [
                'status' => SeatManage::SEAT_STATUS_ON,
                'in' =>['work_status' => [SeatManage::SEAT_WORK_STATUS_FREE,SeatManage::SEAT_WORK_STATUS_BUSY] ]
            ]);
        if($free){
            $done_data = $this->visaModel
                ->where('visa_time','>',$dateUnix)
                ->whereIn('status', FastVisa::$visaFinishedStatusList)
                ->whereIn('seat_id',  array_column($free,'id'))
                ->get()->toArray();
            if($done_data){
                $free_map = array_column($free,null,'id');
                $datas = array_group_by($done_data,'seat_id');
                foreach ($datas as $k => $v) {
                    $res[$k]['name'] = $free_map[$k]['fullname'];
                    foreach ($v as $kk => $vv) {
                        switch ($vv['status']) {
                            case FastVisa::VISA_STATUS_AGREE:
                                $res[$k]['pass'] = empty($res[$k]['pass'])? 1: ++$res[$k]['pass'];
                                break;
                            case FastVisa::VISA_STATUS_REFUSE:
                                $res[$k]['refuse'] = empty($res[$k]['refuse'])? 1: ++$res[$k]['refuse'];
                                break;
                            case FastVisa::VISA_STATUS_SKIP:
                                $res[$k]['jump'] = empty($res[$k]['jump'])? 1: ++$res[$k]['jump'];
                                break;
                        }
                    }
                    $count = $res[$k];
                    unset($count['name']);
                    $res[$k]['dealed'] = array_sum($count);
                }
            }
        }
        $SeatManageRepository = new SeatManageRepository();
        //当前用户处理信息
        $statistics =$SeatManageRepository->get_dealed_with_order($seat_id);
        if(!empty($statistics['hang'])){
            $visaCount = $visaCount+$statistics['hang'];
        }
        //平均面签处理时间
        $timeReceiveToVisa = $visaRepository->getTimeReceiveToVisa($dateUnix);
        $timeReceiveToVisa = $timeReceiveToVisa > 0 ? gmdate("H:i:s", $timeReceiveToVisa) : 0;
        return $this->showMsg(20000, self::MSG_SUCCESS,[
            'visaCount' => $visaCount,
            'complate_visaCount' => $complate_visaCount,
            'time_receive_to_visa' => $timeReceiveToVisa,
//            'topinfo' => $top
            'free' => $free,
            'res' => $res,
            'statistics' => $statistics
        ]);
	}

	public function getHomeChartsData() {
        $res = (new SeatManageRepository())->getHomeChartsData();
        return json_encode($res);
	}
}

?>