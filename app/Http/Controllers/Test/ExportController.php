<?php
namespace App\Http\Controllers\Test;

use App\Library\Common;
use App\Models\VideoVisa\FastVisa;
use App\Models\Xin\CarHalfApply;
use App\Models\XinFinance\CarLoanOrderMore;
use App\Models\XinFinance\TaskTimeDetail;
use DB;
use App\Http\Controllers\BaseController;

class ExportController extends BaseController {

    protected $visa_obj;
    protected $car_loan_order_more_obj;
    protected $task_time_detail_obj;
    protected $car_half_apply_obj;
    protected $send_email = 'lihongtian@xin.com';


    public function __construct()
    {
        parent::__construct();
        if(!is_private_ip()){
            header('localtion:/');
            exit();
        }
        $this->visa_obj = new FastVisa();
        $this->car_loan_order_more_obj = new CarLoanOrderMore();
        $this->task_time_detail_obj = new TaskTimeDetail();
        $this->car_half_apply_obj = new CarHalfApply();
    }

    /**
    1.时间段：视频面签开通 至 今
    2.视频面签清单，含：客户姓名、手机号、身份证号、城市、提交日期、提交时间、任务完成时间、视频面签结果
    3.视频面签客户的电子签完成时间 xin_finance car_loan_order_more.e_contract_confirm_time字段(电子签完成时间，驳回会更新此字段)
    4.视频面签客户提交放款进件的日期、时间 xin_finance.task_time_detail loan_area_submit_time字段
     */
    public function index_old()
    {
        static $id = 0 ;
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $fileName = "visa_data_" . date("Ymd_His") . ".csv";
        $fp = fopen(public_path()."/".$fileName, 'w');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);
        $data = iconv("UTF-8", "gbk", '"apply_id","客户姓名","手机号","身份证号","城市","提交时间","任务完成时间","视频面签结果","电子签完成时间","放款进件时间"') . "\n";
        fwrite($fp,$data); // 写入数据
        DB::setFetchMode(\PDO::FETCH_ASSOC);
        $db = DB::connection('mysql.xin::read');
        while (true) {
            $res = $this->visa_obj->getAll(
                ["id","apply_id","full_name","mobile","id_card_num","line_up_time","visa_time","status","master_id"],
                ["id >" => $id , "in" => ["status" => [5,6,7]]],
                ["id" => "asc"],
                [],
                true,
                false,
                2000
            );
            if(empty($res)){
                break;
            }
            $id = max(array_column($res,'id'));
            $apply_ids = array_filter(array_unique(array_column($res,"apply_id")));
            #电子签完成时间
            $e_contract_confirm_times = $this->car_loan_order_more_obj->getAll(["applyid","e_contract_confirm_time"],['in' => ["applyid" => $apply_ids]]);
            $e_contract_confirm_times = array_column($e_contract_confirm_times,null,'applyid');
            #放款进件时间
            $loan_area_submit_times = $this->task_time_detail_obj->getAll(["applyid","loan_area_submit_time"],['in' => ["applyid" => $apply_ids]]);
            $loan_area_submit_times = array_column($loan_area_submit_times,null,'applyid');
            #销售城市
            $master_ids = implode(',',array_filter(array_unique(array_column($res,"master_id"))));
            $sql = "SELECT rm.masterid as master_id,c.cityname as cityname from rbac_master rm LEFT JOIN city c on rm.cityid = c.cityid where rm.masterid in ({$master_ids});";
            $master_city = $db->select($sql);
            $master_city_arr = array_column($master_city,'cityname','master_id');
            foreach ($res as $val) {
                $applyid = $val['apply_id'];
                switch ($val['status']){
                    case 5:
                        $status = "审核通过";
                        break;
                    case 6:
                        $status = "审核拒绝";
                        break;
                    case 7:
                        $status = "跳过面签";
                        break;
                    default:
                        $status = "未知";
                }
                $temp = [
                    "apply_id" => $applyid,
                    "客户姓名" => $val['full_name'],
                    "手机号" => $val['mobile'],
                    "身份证号" => $val['id_card_num'],
                    "城市" => $master_city_arr[$val['master_id']],
                    "提交时间" => date("Y-m-d H:i:s",$val['line_up_time']),
                    "任务完成时间" => date("Y-m-d H:i:s",$val['visa_time']),
                    "视频面签结果" => $status,
                    "电子签完成时间" => $e_contract_confirm_times[$applyid]['e_contract_confirm_time'],
                    "放款进件时间" => $loan_area_submit_times[$applyid]['loan_area_submit_time']
                ];
                //加入excel
                $data = iconv("UTF-8", "gbk//TRANSLIT", '"' . implode('","', $temp)) . "\"\n";
                fwrite($fp,$data); // 写入数据

            }

        }
        fclose($fp); //关闭文件句柄
        echo public_path()."/".$fileName;
        exit;
    }

    public function index(){
        static $id = 0 ;
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $fileName = "visa_data_" . date("Ymd_His") . ".csv";
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);
        echo iconv("UTF-8", "gbk", '"apply_id","客户姓名","手机号","身份证号","城市","提交时间","任务完成时间","视频面签结果","电子签完成时间","放款进件时间"') . "\n";
        // 输出Excel列名信息
//        $head = array("apply_id","客户姓名","手机号","身份证号","城市","提交时间","任务完成时间","视频面签结果","电子签完成时间","放款进件时间");
//        foreach ($head as $i => $v) {
//            // CSV的Excel支持GBK编码，一定要转换，否则乱码
//            $head[$i] = iconv('utf-8', 'gb2312', $v);
//        }
        // 将数据通过fputcsv写到文件句柄
//        fputcsv($fp, $head);
        // 计数器
        $cnt = 0;
        // 每隔$limit行，刷新一下输出buffer，不要太大，也不要太小
        $limit = 100000;
        DB::setFetchMode(\PDO::FETCH_ASSOC);
        $db = DB::connection('mysql.xin::read');
        while (true) {
            $res = $this->visa_obj->getAll(
                ["id","apply_id","full_name","mobile","id_card_num","line_up_time","visa_time","status","master_id"],
                ["id >" => $id , "in" => ["status" => [5,6,7]]],
                ["id" => "asc"],
                [],
                true,
                false,
                2000
            );
            if(empty($res)){
                break;
            }
            $id = max(array_column($res,'id'));
            $apply_ids = array_filter(array_unique(array_column($res,"apply_id")));
            #电子签完成时间
            $e_contract_confirm_times = $this->car_loan_order_more_obj->getAll(["applyid","e_contract_confirm_time"],['in' => ["applyid" => $apply_ids]]);
            $e_contract_confirm_times = array_column($e_contract_confirm_times,null,'applyid');
            #放款进件时间
            $loan_area_submit_times = $this->task_time_detail_obj->getAll(["applyid","loan_area_submit_time"],['in' => ["applyid" => $apply_ids]]);
            $loan_area_submit_times = array_column($loan_area_submit_times,null,'applyid');
            #销售城市
            $master_ids = implode(',',array_filter(array_unique(array_column($res,"master_id"))));
            $sql = "SELECT rm.masterid as master_id,c.cityname as cityname from rbac_master rm LEFT JOIN city c on rm.cityid = c.cityid where rm.masterid in ({$master_ids});";
            $master_city = $db->select($sql);
            $master_city_arr = array_column(json_decode(json_encode($master_city),true),'cityname','master_id');
            foreach ($res as $val) {
                $t = [];
//                $cnt ++;
//                if ($limit == $cnt) { //刷新一下输出buffer，防止由于数据过多造成问题
//                    ob_flush();
//                    flush();
//                    $cnt = 0;
//                }
                $applyid = $val['apply_id'];
                switch ($val['status']){
                    case 5:
                        $status = "审核通过";
                        break;
                    case 6:
                        $status = "审核拒绝";
                        break;
                    case 7:
                        $status = "跳过面签";
                        break;
                    default:
                        $status = "未知";
                }
                $temp = [
                    "apply_id" => $applyid,
                    "客户姓名" => $val['full_name'],
                    "手机号" => $val['mobile'],
                    "身份证号" => $val['id_card_num']."\t",
                    "城市" => isset($master_city_arr[$val['master_id']])? $master_city_arr[$val['master_id']]:'',
                    "提交时间" => !empty($val['line_up_time'])? date("Y-m-d H:i:s",$val['line_up_time']):'',
                    "任务完成时间" => !empty($val['visa_time'])? date("Y-m-d H:i:s",$val['visa_time']):'',
                    "视频面签结果" => $status,
                    "电子签完成时间" => isset($e_contract_confirm_times[$applyid]['e_contract_confirm_time'])  && $e_contract_confirm_times[$applyid]['e_contract_confirm_time'][0] != '0'? $e_contract_confirm_times[$applyid]['e_contract_confirm_time']:'',
                    "放款进件时间" => isset($loan_area_submit_times[$applyid]['loan_area_submit_time']) && $loan_area_submit_times[$applyid]['loan_area_submit_time'][0] != '0'? $loan_area_submit_times[$applyid]['loan_area_submit_time']:''
                ];
//                foreach ($temp as $i => $v) {
//                    // CSV的Excel支持GBK编码，一定要转换，否则乱码
//                    $t[$i] = iconv('utf-8', 'gb2312', $v);
//                }
                echo iconv("UTF-8", "gbk//TRANSLIT", '"' . implode('","', $temp)) . "\"\n";
            }

        }
    }

}