<?php

namespace App\Console\Commands;
use App\Library\Common;
use App\Library\RedisCommon;
use DB;

//php artisan killprocess --key=test.php
class KillProcess extends BaseCommand
{

    protected $signature = 'killprocess {func} {name?} {check?}';
    protected $description = '手动杀进程';

    /**
     * 自动调用方法 php artisan killprocess kill_process autoapplycron check
     */
    public function handle()
    {
        $func = $this->argument('func');
        $this->{$func}();
    }

    public function kill_process()
    {
        $key = $this->argument('name');
        $check = $this->argument('check');
        if(empty($key)){
            exit("需要kill的进程名为空!");
        }
        if(empty($check)){
            echo "开始进行kill操作".PHP_EOL;
        }
        $ee = "ps -ef |grep -i   ".$key." | grep -v 'grep' | grep -v 'killprocess' | wc -l";
        $ee1 = "ps -ef |grep -i  '$key' | grep -v 'grep' | grep -v 'killprocess'";
        $return_res = `$ee1`;
        echo '查询结果'.$return_res."\n";
        $total_process =  `$ee`;
        echo '进程数'.$total_process."\n";
        if(!empty($check)){
            echo "查询完毕".PHP_EOL;
            exit();
        }
        $total_process = (int)trim($total_process);
        $return = empty($total_process)?0:$total_process;
        echo $return."个进程存在\n"."已杀掉！";
        $ff = "ps -ef |grep  '{$key}' | grep -v 'grep' | awk '{print $2}' |xargs kill -9";
        echo $ff."\n";
        `$ff`;
        echo 'ok!!!';
    }

    /**
     * @note 检查守护进程是否在执行 php artisan killprocess check_is_active
     */
    public function check_is_active()
    {
        $redis_obj = new RedisCommon();
        $last_time = $redis_obj->get('autoApplyCron_redis_obj_active_key');
        if(empty($last_time)){
            $last_time = 0;
        }
        echo "上次分单执行时间为----->".$last_time.PHP_EOL;
        $temp_time = time() - $last_time;
        echo "上次分单执行时间距离当前时间为----->".$temp_time.PHP_EOL;
        #一分钟没有更新则杀掉进程，cron重启
        if($temp_time > 60){
            echo "杀掉分单脚本----->".time().PHP_EOL;
            $redis_obj->setex('autoApplyCron_redis_obj_active_key',time(),3600);
            Common::sendMail('分单脚本挂了,已重启!','挂掉时间:'.$last_time);
            $this->__kill("autoapplycron");
        }
    }
    
}