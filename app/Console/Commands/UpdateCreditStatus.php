<?php

namespace App\Console\Commands;

use App\Models\VideoVisa\FastVisa;
use DB;
use App\Library\Common;
use App\Repositories\Api\InitialFaceRepository;
class UpdateCreditStatus extends BaseCommand
{
    protected $signature = 'cron:updatecreditstatus';
    protected $description = '更新erp信审状态，是人工信审还是机器信审';
    protected $visaObj;
    protected $api_error_email;

    public function __construct()
    {
        parent::__construct();
        $this->visaObj = new FastVisa();
        $this->api_error_email = config('mail.developer');
    }

    public function handle()
    {
        $arr = [];
        $VisaCpunt = $this->visaObj->countBy(['not_in' => ['status' => [5,6,7]],'erp_credit_status'=>0]);
        $this->output->progressStart($VisaCpunt);
        $VisaList = $this->visaObj->getAll(['apply_id'], ['not_in' => ['status' => [5,6,7]],'erp_credit_status'=>0]);
        //获取未完成订单，查询erp信审状态
        foreach ($VisaList as $visaInfo) {
            $applyid = $visaInfo['apply_id'];
            $res = InitialFaceRepository::credit_info($applyid);
            $res = !empty($res[$applyid])? $res[$applyid]:0;
            $arr[$res][] = $applyid;
            $this->output->progressAdvance();
        }

        if($arr){
            //更新数据
            try {
                DB::connection('mysql.video_visa')->beginTransaction();
                foreach ($arr as $k => $v) {
                    $status = (integer)$k;
                    if(!$status){
                        continue;
                    }
                    $VisaList = $this->visaObj->updateBy(['erp_credit_status' => $status], ['in' => ['apply_id' => $v]]);
                    $info = FastVisa::$visaErpStatusChineseMap[$status];
                    $this->comment("\n本次修改状态为->{$info},影响行数为->".$VisaList);
                }
                DB::connection('mysql.video_visa')->commit();
            }catch (\Exception $e){
                DB::connection('mysql.video_visa')->rollback();
                $msg = $e->getMessage();
                $trace = $e->getTraceAsString();
                @Common::sendMail('修改erp信审状态异常','错误信息'.'<p>msg:' . $msg . '<p>trace:' . $trace , $this->api_error_email);
                $this->error("Something Error!");
            }
        }
        $this->output->progressFinish();
        $this->info("Successful!");

    }
}