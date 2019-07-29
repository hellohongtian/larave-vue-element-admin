<?php

namespace App\Console\Commands;

use App\Models\VideoVisa\FastVisa;
use App\Models\Xin\CarDetail;
use App\Models\Xin\CarHalfApply;

class SyncIdcardAndVin extends BaseCommand
{
    protected $description = '同步id_card_num和vin到fast_visa表';
    protected $signature = 'syncidcardandvin {func}';
    private $pool;
    private $workers = [];//swoole_process[] 记录所有worker的process对象
    private $used_workers = [];//记录worker工作状态
    private $start_worker_num = 10;//初始进程数
    private $curr_num;//当前进程数
    private $active_time = [];//闲置进程时间戳

    const FAST_SWOOLE_KEY = 'fast_swoole_key'; //swoole_table的key
    const FAST_SWOOLE_LIMIT = 1000; //每次处理的条数

    public function handle()
    {
        $func = $this->argument('func');
        $this->{$func}();
    }

    private function multi_process()
    {
        $table = new \swoole_table(1024);
        $table->column('final_visa_id', \swoole_table::TYPE_INT, 4);
        $table->column('need_time',\swoole_table::TYPE_INT, 4);
        $table->create();
        $table->set(self::FAST_SWOOLE_KEY, ['final_visa_id' => 0]);
        $table->set(self::FAST_SWOOLE_KEY, ['need_time' => microtime(true)]);

        $this->pool = new \swoole_process(function () use ($table) {
            // 循环建立worker进程
            for ($i = 0; $i < $this->start_worker_num; $i++) {
                $this->createWorker();
            }
            echo '初始化进程数：' . $this->curr_num . PHP_EOL;
            // 每秒定时往闲置的worker的管道中投递任务
            \swoole_timer_tick(1000, function ($timer_id) use ($table) {
                foreach ($this->used_workers as $pid => $used) {
                    if ($used == 0) {//空闲
                        $str_1 = "vvvvvvvvvvv存在空闲进程---->{$pid}vvvvvvvvvvvv";
                        echo "\033[1;30;47;9m$str_1 \e[0m\n";
                        $this->used_workers[$pid] = 1;// 标记使用中
                        $this->active_time[$pid] = time();//任务开始时间
                        $final_visa_id = $this->get_final_visa_id($table);
                        if($final_visa_id !== false){
                            $data = json_encode([
                                'final_visa_id' => $final_visa_id,
                                'pid' => $pid
                            ]);
                            $this->workers[$pid]->write($data);//投递任务
                        }else{
                            $this->destroy($pid);
                        }
                        break;
                    }
                }
                if ($this->curr_num == 0) {
                    foreach ($this->workers as $pid => $worker) {
                        $worker->write('exit');
                    }
                    \swoole_timer_clear($timer_id);// 关闭定时器
                    $this->pool->exit(0);// 退出进程池
                    exit();
                }
            });
        });
        $master_pid = $this->pool->start();
        echo "Master $master_pid start\n";
        while ($ret = \swoole_process::wait()) {
            $pid = $ret['pid'];
            echo "process {$pid} 回收\n";
            if($pid == $master_pid){
                $cost_time = sprintf('%.2f',(microtime(true)-($table->get(self::FAST_SWOOLE_KEY,'need_time')))/60);
                echo "总耗时-------> {$cost_time} 分钟\n";
            }
        }
    }
    /**
     * 创建一个新进程
     * @return int 新进程的pid
     */
    private function createWorker()
    {
        //$worker_process是父进程
        $worker_process = new \swoole_process(function (\swoole_process $worker){
            // 给子进程管道绑定事件
            \swoole_event_add($worker->pipe, function ($pipe) use ($worker) {
                $data = trim($worker->read());//读取父进程write内容
                if ($data == 'exit') {
                    $worker->exit(0);//退出子进程 0表示正常结束，会继续执行PHP的shutdown_function，其他扩展的清理工作。
                    exit();
                }
                if($data){
                    $data = json_decode($data,true);
                    $this->update_id_card_num_and_vin($data['final_visa_id'],$data['pid']);
                }
                $worker->write("complete");// 返回结果，表示任务彻底结束
            });
        });
        /**$process->pid 属性为子进程的PID $process->pipe 属性为管道的文件描述符*/
        $worker_pid = $worker_process->start(); //创建成功返回子进程的PID
        // 给父进程管道绑定事件
        \swoole_event_add($worker_process->pipe, function ($pipe) use ($worker_process) {
            $data = trim($worker_process->read());
            if ($data == 'complete') {
                $this->used_workers[$worker_process->pid] = 0;// 标记为空闲
            }
        });
        //记录所有worker的process对象
        $this->workers[$worker_pid] = $worker_process;
        //标记为空闲  记录worker工作状态
        $this->used_workers[$worker_pid] = 0;
        //闲置进程时间戳
        $this->active_time[$worker_pid] = time();
        //当前进程数
        $this->curr_num = count($this->workers);
        return $worker_pid;
    }
    /**
     * 更新身份证和车辆vin码
     * @param $final_visa_id
     * 身份证查询在xin库的car_half_apply
     * vin 在xin库的car_detail
     * $fields = ['color_remark','vin','engine_num'];
     * $this->car_detail_info[$carid] = $this->car_detail_model->getOne($fields,['carid'=>$carid]);
     * @return bool
     */
    private function update_id_card_num_and_vin($final_visa_id,$pid)
    {
        $visa_model = new FastVisa();
        $car_half_apply_model = new CarHalfApply();
        $car_detail_model = new CarDetail();
        $data = $visa_model->getAll(['id','apply_id','car_id'],['id >' => $final_visa_id],['id' => 'asc'], [], true, false, self::FAST_SWOOLE_LIMIT);
        if($data){
            $apply_arr = array_column($data,'apply_id');
            $carid_arr = array_column($data,'car_id');
            $id_card_num_arr = $car_half_apply_model->getAll(['id_card_num','applyid'],['in' => ['applyid' => $apply_arr]]);
            $vin_arr = $car_detail_model->getAll(['vin','carid'],['in' => ['carid' => $carid_arr]]);
            if($id_card_num_arr){
                $id_card_num_arr = array_column($id_card_num_arr,'id_card_num','applyid');
            }
            if($vin_arr){
                $vin_arr = array_column($vin_arr,'vin','carid');
            }
            foreach ($data as $val){
                $str = "更新面签ID->".$val['id']."--进程id->".$pid;
                echo "\033[1;37;42;9m$str \e[0m\n";
                $visa_model->updateBy(
                    [
                        'id_card_num' => !empty($id_card_num_arr[$val['apply_id']])? $id_card_num_arr[$val['apply_id']]:'',
                        'vin' => !empty($vin_arr[$val['car_id']])? $vin_arr[$val['car_id']]:''
                    ],
                    [
                        'id' => $val['id']
                    ]);
            }
            echo "\n";
        }
    }
    /**
     * @note 更新获取最后的visa_id
     * @param object $table
     * @return int
     */
    private function get_final_visa_id($table)
    {
        $final_visa_id = $table->get(self::FAST_SWOOLE_KEY,'final_visa_id');
        echo "\n final_visa_id---------->".$final_visa_id."\n";
        $visa_model = new FastVisa();
        $visa_res = $visa_model->getAll(['id'],['id >' => $final_visa_id],['id' => 'asc'],[],true,false,self::FAST_SWOOLE_LIMIT);
        if(empty($visa_res)){
            return false;
        }
        if($visa_res){
            $visa_res = array_column($visa_res,'id');
            $new_id = max($visa_res);
            if($new_id == $final_visa_id){
                return false;
            }
        }
        $table->set(self::FAST_SWOOLE_KEY, ['final_visa_id' => $new_id ]);
        return $final_visa_id;
    }

    private function destroy($pid)
    {
        $this->workers[$pid]->write('exit');
        unset($this->workers[$pid]);
        $this->curr_num = count($this->workers);
        unset($this->used_workers[$pid]);
        unset($this->active_time[$pid]);
        echo "------------------开始销毁子进程,当前进程pid----{$pid}------------------\n";
    }

    /**
     * 单进程
     * @return bool
     */
    private function do_thing()
    {
        $visa_model = new FastVisa();
        $car_half_apply_model = new CarHalfApply();
        $car_detail_model = new CarDetail();
        static $final_visa_id = 0;
        while (true){
            echo "\n final_visa_id---------->".$final_visa_id."\n";
            $data = $visa_model->getAll(['id','apply_id','car_id'],['id >' => $final_visa_id],['id' => 'asc'], [], true, false, self::FAST_SWOOLE_LIMIT);
            if($data){
                $apply_arr = array_column($data,'apply_id');
                $carid_arr = array_column($data,'car_id');
                $final_visa_id = max(array_column($data,'id'));
                $id_card_num_arr = $car_half_apply_model->getAll(['id_card_num','applyid'],['in' => ['applyid' => $apply_arr]]);
                $vin_arr = $car_detail_model->getAll(['vin','carid'],['in' => ['carid' => $carid_arr]]);
                if($id_card_num_arr){
                    $id_card_num_arr = array_column($id_card_num_arr,'id_card_num','applyid');
                }
                if($vin_arr){
                    $vin_arr = array_column($vin_arr,'vin','carid');
                }
                foreach ($data as $val){
                echo "update,car_id->".$val['car_id']."--apply_id->".$val['apply_id']."\n";
                    $visa_model->updateBy(
                        [
                            'id_card_num' => !empty($id_card_num_arr[$val['apply_id']])? $id_card_num_arr[$val['apply_id']]:'',
                            'vin' => !empty($vin_arr[$val['car_id']])? $vin_arr[$val['car_id']]:''
                        ],
                        [
                            'id' => $val['id']
                        ]);
                }
                echo "\n";
            }else{
                return false;
            }
        }
    }
}