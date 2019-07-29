<?php

namespace App\Console\Commands;

use App\Models\VideoVisa\FastVisa;
use App\Models\XinFinance\CarLoanOrderMore;
use DB;

class ExportData0509 extends BaseCommand
{
    protected $signature = 'cron:export0509';
    protected $description = '导数据';
    protected $visa_obj;
    protected $car_loan_order_more_obj;
    protected $task_time_detail_obj;
    protected $api_error_email;

    public function __construct()
    {
        parent::__construct();
        $this->visa_obj = new FastVisa();
        $this->car_loan_order_more_obj = new CarLoanOrderMore();
        $this->task_time_detail_obj = new CarLoanOrderMore();
        $this->api_error_email = config('mail.developer');
    }

    /**
    1.时间段：视频面签开通 至 今
    2.视频面签清单，含：客户姓名、手机号、身份证号、城市、提交日期、提交时间、任务完成时间、视频面签结果
    3.视频面签客户的电子签完成时间 xin_finance car_loan_order_more.e_contract_confirm_time字段(电子签完成时间，驳回会更新此字段)
    4.视频面签客户提交放款进件的日期、时间 xin_finance.task_time_detail loan_area_submit_time字段
     */
    public function handle()
    {
        static $id = 0 ;
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $fileName = "视频面签数据-" . date("Ymd_His") . ".csv";
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);
        echo iconv("UTF-8", "gbk", '"apply_id","客户姓名","手机号","身份证号","城市","提交时间","任务完成时间","视频面签结果","电子签完成时间","放款进件时间"') . "\n";
        $params = [];
        while (1) {
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
            DB::setFetchMode(\PDO::FETCH_ASSOC);
            $master_ids = implode(',',array_filter(array_unique(array_column($res,"master_id"))));
            $sql = "SELECT rm.masterid as master_id,c.cityname as cityname from rbac_master rm LEFT JOIN city c on rm.cityid = c.cityid where rm.masterid in ({$master_ids});";
            $master_city = DB::select($sql);
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
                    "城市" => '',
                    "提交时间" => date("Y-m-d H:i:s",$val['line_up_time']),
                    "任务完成时间" => date("Y-m-d H:i:s",$val['visa_time']),
                    "视频面签结果" => $status,
                    "电子签完成时间" => $e_contract_confirm_times[$applyid]['e_contract_confirm_time'],
                    "放款进件时间" => $loan_area_submit_times[$applyid]['loan_area_submit_time']
                ];
            //加入excel
            echo iconv("UTF-8", "gbk//TRANSLIT", '"' . implode('","', $temp)) . "\"\n";
            }

        }
        exit;
    }
}