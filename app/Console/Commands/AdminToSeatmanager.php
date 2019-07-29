<?php

namespace App\Console\Commands;

use App\Library\Helper;
use App\Models\VideoVisa\Admin;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\SeatManage;
use App\Repositories\ImRepository;
use App\Repositories\SeatManageRepository;
use DB;
use App\Library\Common;
use App\Repositories\Api\InitialFaceRepository;
class AdminToSeatmanager extends BaseCommand
{
    protected $signature = 'cron:admintoseatmanager';
    protected $description = '迁移admin到seat_manager';
    protected $adminObj;
    protected $seatmanagerObj;
    protected $seat_pos;
    protected $api_error_email;
    protected $check_list = [

    ];
    private $env = '';
    private $seat_key = '';


    public function __construct()
    {
        parent::__construct();
        $this->adminObj = new Admin();
        $this->seatmanagerObj = new SeatManage();
        $this->api_error_email = config('mail.developer');
        $this->env = Helper::isProduction() ? 'production' : '';
        $this->seat_pos = new SeatManageRepository();

    }

    public function handle()
    {
        //更新数据
        try {
            DB::connection('mysql.video_visa')->beginTransaction();
            $admin_count = $this->adminObj->countBy();
            $this->output->progressStart($admin_count);
            $adminList = $this->adminObj->getAll(['*']);
            //获取未完成订单，查询erp信审状态
            foreach ($adminList as $admin) {
                $email = $admin['email'];
                $status = $admin['status'];
                $masterid = $admin['masterid'];
                $seat_manager = $this->seatmanagerObj->getOne(['id', 'email', 'status'], ['email' => $email]);
                $roleid = 1;
                $arr = [
                    'roleid' => $roleid,
                    'flag' => 3,
                    'master_id' => $admin['masterid'],
                    'mastername' => $admin['mastername'],
                    'fullname' => $admin['fullname'],
                    'gender' => $admin['gender'],
                    'deptname' => $admin['deptname'],
                    'mobile' => $admin['mobile'],
                    'email' => $admin['email'],
                    'im_account_id' => 0,
                    'status' => $admin['status'],
                    'work_status' => SeatManage::SEAT_WORK_STATUS_OFFLINE,
                    'create_time' => $admin['create_time'],
                    'update_time' => $admin['update_time'],
                ];
                if (in_array($masterid, $this->check_list)) {//复审坐席配置
                    $arr['roleid'] = 3;
                    if(!empty($seat_manager)){
                        $this->seatmanagerObj->updateBy(['roleid' =>$arr['roleid'],'status' => 1 ],['email' => $email]);
                    }else{
                        //加入seat_manager表
                        $this->create_accid($arr);
                    }
                }else{
                    if(!empty($seat_manager) && $seat_manager['status'] == 2){//seat_manager坐席为禁用
                        $this->seatmanagerObj->updateBy(['roleid' =>1,'flag'=>2,'status' => $status ],['email' => $email]);
                    }elseif(!empty($seat_manager) && $seat_manager['status'] == 1){
                        $this->seatmanagerObj->updateBy(['roleid' =>2,'flag'=>3,'status' => $status ],['email' => $email]);
                    }elseif(empty($seat_manager)){
                        $arr['flag'] = 2;
                        $this->create_accid($arr);
                    }else{
                        $this->seatmanagerObj->updateBy(['roleid' =>$roleid,'flag'=>2,'status' => $status ],['email' => $email]);
                    }
                }

                $this->output->progressAdvance();
            }
            $seat_list = $this->seatmanagerObj->getAll(['*']);
            if(!empty($seat_list)){
                foreach ($seat_list as $vv){
                    if($vv['flag'] == 3){
                        $this->seatmanagerObj->updateBy(['roleid' =>2,'flag'=>3,'status' => 1 ],['email' => $vv['email']]);
                    }
                    if(in_array($vv['email'], $this->check_list)){
                        $this->seatmanagerObj->updateBy(['roleid' =>3,'flag'=>3,'status' => 1 ],['email' => $vv['email']]);
                    }

                }
            }
            DB::connection('mysql.video_visa')->commit();
        }catch (\Exception $e){
            DB::connection('mysql.video_visa')->rollback();
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            @Common::sendMail('导数据异常','错误信息'.'<p>msg:' . $msg . '<p>trace:' . $trace , $this->api_error_email);
            $this->error("Something Error!");
        }
        $this->output->progressFinish();
        $this->info("Successful!");
    }
    /**
     * 创建im账号
     */
    public function create_accid($data)
    {
        $time = time();
        //添加网易账号新
        $account_params = [
            'accid' => $data['mastername'] . '_' . $this->env . '_seat',
            'token' => '',
            'gender' => $data['gender'],
            'nickname' => $data['fullname'],
            'create_time' => $time,
            'update_time' => $time,
        ];
        $add_im = $this->seat_pos->addImAccount($account_params);

        $data['im_account_id'] = $add_im;
        $this->seatmanagerObj->insertGetId($data);
    }
}