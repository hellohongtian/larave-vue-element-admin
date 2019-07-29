<?php

namespace App\Console\Commands;

use App\Models\RiskStat\DecisionData;
use App\Models\VideoVisa\ErrorCodeLog;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaReport;
use DB;
use App\Library\Common;
use App\Repositories\Api\InitialFaceRepository;
class CreateSeatReport extends BaseCommand
{
    protected $signature = 'cron:create_seat_report';
    protected $description = '生成坐席每日统计数据';
    protected $visa_obj;
    protected $report_obj;
    protected $api_error_email;

    public function __construct()
    {
        parent::__construct();
        $this->visa_obj = new FastVisa();
        $this->report_obj = new FastVisaReport();
        $this->api_error_email = config('mail.developer');
    }

    public function handle()
    {
        $start_data = strtotime(date('Y-m-d').'-1 day');
        $end_data = strtotime(date('Y-m-d'));
        $report_cpunt = $this->report_obj->countBy();
        $where = $fast_visa_log_where = '';
        #表里还没有数据时,代表第一次同步
        if(!empty($report_cpunt)){
            $this->info('判断今日是否已执行过脚本...');
            $check =  $this->report_obj->countBy(['date' => date('Y-m-d',$start_data)]);
            if($check){
                $this->info('!!!今日已执行过脚本,请勿重复执行!!!');
                return false;
            }
            $this->info("开始每日导入,插入数据表!");
            $where = "  and create_time < $end_data and create_time > $start_data ";
            $fast_visa_log_where = "  and UNIX_TIMESTAMP(`created_at`) < $end_data and UNIX_TIMESTAMP(`created_at`) > $start_data ";
        }else{
            $this->info("开始第一次导入,插入数据表!");
        }
        DB::setFetchMode(\PDO::FETCH_ASSOC);
        $seat_sql = "SELECT time_log.seat_id,time_log.fullname as seat_name,time_log.op_date as `date`,time_log.on_duty_time,time_log.off_duty_time,time_log.total_free_time,time_log.total_busy_time,time_log.total_leave_time,
time_log.total_free_time+time_log.total_busy_time as total_online_time,
IFNULL(visa_log.pass_count,0) as pass_count,IFNULL(visa_log.refuse_count,0) as refuse_count,IFNULL(visa_log.jump_count,0) as jump_count, IFNULL(visa_log.pass_count+visa_log.refuse_count,0) as pass_refuse_count,
IFNULL(visa_log.total_queue_time/(visa_log.pass_count+visa_log.refuse_count+visa_log.jump_count),0) as avg_queue_time,
IFNULL(visa_log.total_do_time/(visa_log.pass_count+visa_log.refuse_count+visa_log.jump_count),0) as avg_deal_time
from (
(SELECT log.seat_id,sm.fullname,log.op_date,MIN(log.create_time) as on_duty_time,
 MAX(CASE log.type WHEN 3 THEN log.create_time ELSE log.end_time END ) as off_duty_time,
  SUM(CASE log.type WHEN 1 THEN log.duration_time ELSE 0 END ) as total_free_time,
  SUM(CASE log.type	WHEN 2 THEN	log.duration_time	ELSE 0 END ) as total_busy_time,
  (SUM(CASE log.type WHEN 3 THEN log.duration_time ELSE 0 END)-MAX(CASE log.type WHEN 3 THEN log.duration_time ELSE 0 END)) as total_leave_time from
(SELECT *,FROM_UNIXTIME(create_time) as online_time from seat_manager_log where type in (1,2,3) and duration_time < 86400 and duration_time >0 $where
 ORDER BY seat_id,op_date,create_time) as log LEFT JOIN seat_manager as  sm on log.seat_id = sm.id GROUP BY log.seat_id,log.op_date) as time_log 
 left join 
(
SELECT visa_data.seat_id,visa_data.visa_day,SUM(queue_time) as total_queue_time,SUM(do_time) as total_do_time,
   MAX(CASE visa_data.visa_status WHEN  5 THEN visa_data.count ELSE 0 END ) AS pass_count,
	 MAX(CASE visa_data.visa_status WHEN  6 THEN visa_data.count ELSE 0 END )  AS refuse_count,
	 MAX(CASE visa_data.visa_status WHEN  7 THEN visa_data.count ELSE 0 END ) AS jump_count 
	 from (SELECT seat_id,SUM(seat_receive_time-queuing_time) as queue_time,SUM(visa_time-seat_receive_time) as do_time,visa_status,FROM_UNIXTIME(visa_time,'%Y-%m-%d') as visa_day,count(*) as count  
	 from fast_visa_log where visa_status in (5,6,7) and queuing_time > 0  and seat_receive_time > 0  and visa_time > 0 and visa_time > 0  $fast_visa_log_where GROUP BY seat_id,visa_day,`visa_status`) 
	 as visa_data 
	 GROUP BY visa_data.seat_id,visa_data.`visa_day`
) as visa_log on time_log.seat_id=visa_log.seat_id and time_log.op_date=visa_log.visa_day);
";
        $visa_res = DB::select($seat_sql);

        if(!empty($visa_res)){
            foreach ($visa_res as $key=>$value){
                if(empty($value['pass_refuse_count']) && empty($value['jump_count'])) {
                    unset($visa_res[$key]);
                    $this->info("处理量为0,删除记录->".$value['seat_name']."->".$value['date']);
                }
            }
            $this->info("总数:".count($visa_res));
            try{
                DB::connection('mysql.video_visa')->beginTransaction();
                #插入表
                $visa_res_chunk = array_chunk($visa_res,200);
                $this->info("200条数据一组,插入数据表!");
                $visa_count = count($visa_res_chunk);
                $this->output->progressStart($visa_count);
                foreach ($visa_res_chunk as $k => $v){
                    DB::table('fast_visa_report')->insert($v);
                    $this->output->progressAdvance();
                }
                DB::connection('mysql.video_visa')->commit();
                $this->output->progressFinish();
            }catch (\Exception $e){
                DB::connection('mysql.video_visa')->rollback();
                $msg = $e->getMessage();
                $trace = $e->getTraceAsString();
                $this->error("数据回滚,出现错误:".$msg);
                var_dump($trace);
                @Common::sendMail('生成数据报表错误','错误信息'.'<p>msg:' . $msg . '<p>trace:' . $trace , $this->api_error_email);
            }
            $this->info("脚本执行完成!");
        }else{
            $this->error("查无数据!");
        }

        try{
            $this->delete_invalid_log();
        }catch (\Exception $e){
            $this->info("删除log出现错误...".$e->getMessage());
        }
    }


    /**
     * 清理多余的log
     */
    private function delete_invalid_log()
    {
        $this->info("开始清除无用log...");
        DB::setFetchMode(\PDO::FETCH_ASSOC);
        #只留一个季度
        $date_time = date('Y-m-d H:i:s',strtotime(date('Y-m-d 00:00:00').'-2 week'));
        $where = ['created_at <' => $date_time];
        $obj_arr = ['ErrorCodeLog','LogRequestFromChaojibao','LogRequestFromErp','LogRequestOut','LogSeatOperation'];
        foreach ($obj_arr as $k => $v) {
            $this->info('检查'.$v.'表是否存在两周前记录!');
            if($v == 'ErrorCodeLog'){
                $obj = new \App\Models\VideoVisa\ErrorCodeLog;
            }elseif($v == 'LogRequestOut' || $v == 'LogSeatOperation'){
                $name = '\App\Models\VideoVisa\Log\\'.$v;
                $obj = new $name;
            }
            else{
                $name = '\App\Models\VideoVisa\\'.$v;
                $obj = new $name;
            }
            $count = $obj->countBy($where);
            $this->info($v.'表存在'.$count.'条多余记录!删除...');
            if($count){
                $obj->deleteBy($where);
            }
            $this->info($v.'表删除完成!');
        }
        $this->info('多余log删除完成!!!');
    }
}