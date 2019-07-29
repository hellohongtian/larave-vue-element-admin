<?php
namespace App\Http\Controllers\Analysis;

use App\Http\Controllers\BaseController;
use App\Library\Common;
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
 * 数据分析统计
 */
class AnalysisController extends BaseController
{
    public $visaModel = null;
    public $manageLog = null;
    public $visaPoolModel = null;
    public $fastVisaLog = null;
    protected $visa_rep = null;

    public function __construct()
    {
        //$this->visaModel = new VisaRemark();
        $this->visaModel = new fastVisa();

        $this->manageLog = new SeatManagerLog();
        $this->visaPoolModel = new VisaPool();
        $this->fastVisaLog = new FastVisaLog();
        $this->visa_rep = new FastVisaRepository();

    }


    public function index(Request $request)
    {
        if($request->isMethod('post')){
            $search_date = $request->input('search_date','');
            if(empty($search_date)){
                return false;
            }
            $search_date = explode(' - ',$search_date);
            if(count($search_date) != 2){
                return false;
            }
            $start_time = $search_date[0];
            $end_time = $search_date[1];
            $res = $this->visaModel->get_analysis_data($start_time,$end_time);
            if($res){
                return $this->showMsg(self::CODE_SUCCESS,'',$res);
            }
            return $this->showMsg(self::CODE_FAIL,'',[]);

        }else{
            return view('analysis.index');
        }
    }


    public function report(Request $request)
    {
        if($request->isMethod('post')){
            $range = $request->input('range','');
            $start = $request->input('start','');
            $end = $request->input('end','');
            $tab = $request->input('tab','');
            $page = $request->input('page',0);
            $limit = $request->input('limit',20);
            $seat_id = $request->input('seat_id',0);
            if(empty($range) || empty($start) || empty($end)){ // 默认
                $range = 'day';
                $start = date('Y-m-d',strtotime(date('Y-m-d ').'-6 day'));
                $end = date('Y-m-d');
            }
            if($page){
                $page = $page -1;
            }
            if(empty($tab)){
                $res = $this->visa_rep->get_all_report_list($range,$start,$end,$page,$limit);
            }else{
                $res = $this->visa_rep->get_seat_report_list($range,$start,$end,$page,$limit,$seat_id);
            }
            if($res){
                return $this->showMsg(0,'',$res['data'],$res['count']);
            }
            return $this->showMsg(self::CODE_FAIL,'',[]);

        }else{
            $seat_list = (new SeatManage())->get_effective_seat();
            return view('analysis.report1',['seat_list' => $seat_list]);
        }
    }

}

?>