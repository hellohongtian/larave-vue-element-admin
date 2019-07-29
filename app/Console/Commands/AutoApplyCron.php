<?php
namespace App\Console\Commands;

use App\Library\Common;

use App\Repositories\Cron\ApplyQuqeRepository;
use App\Library\RedisCommon;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\SeatManage;
use App\Repositories\FastVisaRepository;
//use Illuminate\Support\Facades\Redis;
use App\Models\VideoVisa\ErrorCodeLog;
/**
 * 队列分单,匹配空闲坐席和订单
 * Class ApplyQuqeCron
 * @package App\Console\Commands
 */
class AutoApplyCron extends BaseCommand
{
    protected $signature = 'autoapplycron {func}';
    protected $description = '队列分单';
    protected $queueRepos;
    protected $seat_key;
    protected $order_key;
    protected $seat_count = 0;
    protected $redis_obj = null;
    protected $seatObj = null;
    protected $allSeatList = null;
    protected $cron_key = 'autoApplyCron1';
    protected $today_unix;


    /**
     * 自动调用方法
     */
    public function handle()
    {
        $this->seat_key = config('common.auto_apply_seat_key');
        $this->order_key = config('common.auto_apply_order_key');
        $this->redis_obj = new RedisCommon();
        $this->today_unix =  strtotime(date('Y-m-d'));
        $func = $this->argument('func');
        $this->{$func}();
    }

    /**
     * @note 每分钟更新一次redis对象
     */
    public function get_redis_obj()
    {
        $last_time = $this->redis_obj->get('autoApplyCron_redis_obj_key');
        if(empty($last_time)){
            $last_time = 0;
        }
        $temp_time = time() - $last_time;

        if($temp_time > 60){
            $this->redis_obj = new RedisCommon();
            $this->redis_obj->setex('autoApplyCron_redis_obj_key',time(),3600);
        }
    }

    /**
     * 自动分单
     * 将排队中的订单自动分给坐席
     * @return mixed
     * @throws
     */
    public function matchQueue(){
        $that = $this;
        //检查任务进程
        if($this->_get_process_num("autoapplycron matchQueue") <= 1){
            $process = new \swoole_process(function (\swoole_process $worker)use($that){
//                cli_set_process_title($that->cron_key);
                while(true){
                    $this->get_redis_obj();

                    #常驻进程存活标记
                    $this->redis_obj->setex('autoApplyCron_redis_obj_active_key',time(),3600);

                    $that->redis_obj->zRem($that->seat_key,'',0);
                    $that->redis_obj->zRem($that->order_key,'',0);
                    $that->seat_count = $that->redis_obj->zCard($that->seat_key);
                    #存在空闲坐席和订单
                    if($that->seat_count){
                        $this->requeue();
                        $order_count = $that->redis_obj->zCard($that->order_key);
                        if($order_count){
                            $that->autoAllotApply();
                        }
                    }else{
                        sleep(1);
                    }

                }
            }, false,false);
            $process->start();
            $process->daemon();
        }
    }

    /**
     * @note 重启
     */
    public function restart($key)
    {
        if($this->get_father_pid($key)){
            $this->stop($key);
        }
        $this->matchQueue();
        $this->__out('重启成功！');
    }
    /**分单
     * @param int $order_start
     * @param array $seat_list
     * @return bool
     */
    public function autoAllotApply()
    {
        $this->redis_obj->zRem($this->order_key,'',0);
        $this->redis_obj->zRem($this->seat_key,'',0);
        $seat_list = $this->redis_obj->zRange($this->seat_key,0,$this->seat_count-1);
        $visaObj = new FastVisa();
        $seat_manager = new SeatManage();
        //先匹配挂起排队订单
        foreach ($seat_list as $temp => $seat){
            $seat = intval($seat);
            #获取挂起排队订单
            $order_arr = $this->redis_obj->zRangeByScore($this->order_key, $seat, $seat, array('limit' => array(0, 1)));
            if(!empty($order_arr)){
                $id = intval(current($order_arr));
                if(!is_production_env()){
                    echo "获取到挂起排队订单id->$id"."\n";
                }
                $visa = $visaObj->getOne(['*'],['id'=>$id]);
                if(empty($visa))
                {
                    $this->redis_obj->zRem($this->order_key,$id);
                    continue;
                }
                if(empty($seat))
                {
                    $this->redis_obj->zRem($this->seat_key,$seat);
                    continue;
                }
                $this->do_match_action($visa,$seat);
                unset($seat_list[$temp]);
            }
        }
        if(empty($seat_list)){
            return false;
        }
        #匹配对应渠道类型
        foreach ($seat_list as $key => $value) {
            $seat = intval($value);
            $seat_type = $seat_manager->getOne(['apply_group'],['id' => $seat]);
            if (!empty($seat_type['apply_group']) ) {
                $sales_key = C('@.common.apply_sale_type_zset_key.'.$seat_type['apply_group']);
                if ($sales_key) {
                    $this->redis_obj->zRem($sales_key,'',0);
                    $sales_list = $this->redis_obj->zRangeByScore($sales_key, 10000, time(), array('limit' => array(0, 1)));
                    if (!empty($sales_list)) {
                        $sales_id = current($sales_list);
                        $sales_info = $visaObj->getOne(['*'],['id'=>intval($sales_id)]);
                        $this->do_match_action($sales_info,$value);
                        unset($seat_list[$key]);
                    }
                }
            }
        }

        #不取挂起排队订单 , 其他订单
        $count = count($seat_list);
        $order_list = $this->redis_obj->zRangeByScore($this->order_key, 10000, time(), array('limit' => array(0, $count)));
        if(empty($order_list)){
            return false;
        }
        if(!is_production_env()){
            echo "收到订单\n";
            var_dump($order_list);
            echo "空闲坐席\n";
            var_dump($seat_list);
        }
        //查询所有订单信息
        $visaWhere = [
            'in'=>['id'=>$order_list]
        ];
        $canAllotVisaList = $visaObj->getAll(['*'], $visaWhere, ['line_up_time'=>'asc']);
        #删除无效订单号
        if(empty($canAllotVisaList)){
            foreach ($order_list as $oo){
                $this->redis_obj->zRem($this->order_key,$oo);
            }
            return false;
        }
        //移除问题订单id，用于订单id不存在时
        $id_arr = array_column($canAllotVisaList,'id');
        $diff_arr = array_diff($order_list,$id_arr);
        if(!empty($diff_arr)){
            if(!is_production_env()){
                echo '问题订单';
                var_dump($diff_arr);
            }
            foreach ($diff_arr as $vv){
                $this->redis_obj->zRem($this->order_key,$vv);
            }
        }
        //随机匹配剩余空闲作息
        if(!empty($canAllotVisaList) && !empty($seat_list)){
            foreach ($canAllotVisaList as $k=>$v){
                //其他挂起订单
                if($v['status'] == FastVisa::VISA_STATUS_HANG_QUEUEING){
                    continue;
                }
                $temp_seat_id = array_shift($seat_list);
                if(!$temp_seat_id){
                    break;
                }
                $this->do_match_action($v,$temp_seat_id);
            }
        }
    }

    /**
     *
     * @param $visa
     * @param $seat_id
     */
    private function do_match_action($visa,$seat_id)
    {
        //队列中删除订单和坐席
        $sales_key = C('@.common.apply_sale_type_zset_key.'.$visa['sales_type']);
        $this->redis_obj->zRem($sales_key,$visa['id']);
        $od_res = $this->redis_obj->zRem($this->order_key,$visa['id']);
        $sd_res = $this->redis_obj->zRem($this->seat_key,$seat_id);
        if(!$od_res || !$sd_res){
            return false;
        }
        if(empty($visa) || empty($seat_id)){
            return false;
        }
        if(!is_production_env()){
            sprintf('匹配订单id->%s到坐席id->%s',$visa['id'],$seat_id);
        }
        $FastVisaRepositoryObj = new FastVisaRepository();
        $res = $FastVisaRepositoryObj->normalVisaAllocSeat($visa,$seat_id);
        if($res){
            if(!empty($visa['remark'])){
                $remark = json_decode($visa['remark'],true);
                $code = $remark['seat_name']."将承租人{$visa['full_name']}指派到您的名下,请处理!";
            }else{
                if($visa['status'] == FastVisa::VISA_STATUS_HANG_QUEUEING){
                    $code = FastVisa::NOTIFY_SEAT_HANG_VISA_BACK;
                }else{
                    $code = FastVisa::NOTIFY_SEAT_NEW_VISA;
                }
            }
            //通知前端坐席订单已分配
            $send_msg = Common::notifyFontNewVisa($seat_id, $code);
            echo '订单id->'.$visa['id'].'-匹配到坐席id->'.$seat_id."\n";
            if ($send_msg !== true) {
                (new ErrorCodeLog())->runLog(11111, $send_msg);
            }
        }
    }

    /**
     * 重推订单,用于意外未入队列
     */
    public function requeue()
    {
        /*******查询可以分配订单*********/
        $visaModel = new FastVisa();
        $res = $visaModel->getAll(['id','seat_id','line_up_time','status','master_id'],
            ['status'=> FastVisa::VISA_STATUS_CAN_DISPATCH_AND_PICK, 'line_up_time >' => $this->today_unix],[],['master_id'],true,true);
        if(!empty($res)){
            $queue_list= $this->redis_obj->zRange($this->order_key, 0,-1);
            $queue_list = array_filter($queue_list);
            if(!empty($queue_list)){
                $queue_list = array_map('intval',$queue_list);
                $master_list = $visaModel->getAll(['master_id'],
                    ['in' =>['id'=>$queue_list], 'line_up_time >' => $this->today_unix]);
                $master_arr = array_flip(array_column($master_list,'master_id'));
                foreach ($res as $key => $item){
                    if(isset($master_arr[$item['master_id']])){
                        unset($res[$key]);
                    }
                }
            }
            if(!empty($res)){
                $order_list = array_column($res,'id');
                $res = array_column($res,null,'id');
                /******找出未进队列订单******/
                $diff_list = array_diff($order_list,$queue_list);
                if(!empty($diff_list)){
                    foreach ($diff_list as $k => $v){
                        $this->redis_obj->zadd($this->order_key,$res[$v]['line_up_time'],$v);
                    }
                }
            }
        }
    }
}