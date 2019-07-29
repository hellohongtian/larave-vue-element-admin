<?php

namespace App\Console\Commands;

use App\Library\RedisCommon;
use Illuminate\Console\Command;

/**
 * Command的基类，做一层单独的封装
 * Class CommandBase
 * @package App\Console\Commands
 */
class BaseCommand extends Command
{
    protected $redisObj;

    public function __construct()
    {
        parent::__construct();
        $this->redisObj = new RedisCommon();
    }

    /**
     * 判断某个脚本是否在执行
     * @param $command
     * @param $action
     * @return bool
     */
    public function isCommandRunning($command, $action)
    {
        if (is_null(shell_exec("ps aux | grep {$command} | grep {$action} | grep -v grep"))) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 杀进程
     * @note
     * @param string $key
     */
    public function __kill($key){
        `ps -ef |grep  '{$key}' | grep -v 'grep' | awk '{print $2}' |xargs kill -9`;
    }

    /**
     * @note 当前pid
     * @param string $key
     */
    public function status($key)
    {
        $command = "ps -ef |grep  '{$key}' | grep -v 'grep' | awk '{print $2}'";
        $this->__out("\n进程：".trim(`$command`));
    }

    /**
     * @note 获取当前pid
     * @param string $key
     * @return int
     * ps -ef |grep  'BigCoreJob' | grep -v 'grep' | awk '{print $2}' |xargs kill -9
     * ps -ef |grep  'BigCoreJob' | grep -v 'grep' | awk '{print $2}'
     */
    public function self_pid($key,$all = false)
    {
        $command = "ps -ef |grep  '{$key}' | grep -v 'grep' | awk '{print $2}'";
        $pid = trim(`$command`);
        if($all){
            return explode("\n",$pid);
        }
        else{
            return intval($pid);
        }
    }

    /**
     * @note 获取主进程
     * @param string $key
     * @return int
     */
    public function get_father_pid($key)
    {
        $father = 0;
        $command = "ps -ef |grep  '{$key}' | grep -v 'grep' | awk '{print $3}'";
        $pid = explode("\n",trim(`$command`));
        if(!empty($pid)){
            if(count($pid) == 1){
                $father = $this->self_pid($key);
            }
            else{
                foreach ($pid as $id){
                    if($id != 1){
                        $father = $id;
                        break;
                    }
                }
            }

        }
        return $father;
    }


    /**
     * @note 是否正在运行
     * @param string $key
     * @return bool
     */
    public function is_run($key)
    {
        if($this->self_pid($key) > 0){
            return true;
        }else{
            return false;
        }
    }


    /**
     * @note 重启
     * @param string $key
     */
    public function restart($key)
    {
        if($this->get_father_pid($key)){
            $this->stop($key);
        }
        $this->start($key);
        $this->__out('重启成功！');
    }


    /**
     * @note 停止
     * @param string $key
     * @param bool $kill_father true 杀死主进程 false不杀主进程
     * @return bool $kill_father
     */
    public function stop($key,$kill_father = true)
    {
        $mainPid = $this->get_father_pid($key);
        $pid = $this->self_pid($key,true);
        if(!is_array($pid) || empty($pid)){
            $this->__out('没有要停止的进程！');
        }
        else{
            //杀子进程
            foreach ($pid as $id){
                if(empty($id)){
                    continue;
                }
                if($id == $mainPid){
                    continue;
                }
                $this->__out('pid:'.$id);
                \swoole_process::kill($id, SIGKILL);
            }
            $this->__out('停止成功！');
            //杀主进程
            if($kill_father){
                $this->__kill($key);
                exit;
            }
        }
        return true;
    }

    /**
     * @note 打印
     * @param string $message
     */
    public function __out($message)
    {
        if(!is_cli() && is_production_env()) return;
        $msg         = '[fast_youxinjinrong_crontab_cli][%s]%s';
        $out_message = sprintf($msg, date('Y-m-d H:i:s'), print_r($message, true)) . "\n";
        print_r($out_message);
        @ob_flush();//把数据从PHP的缓冲（buffer）中释放出来。
        @flush();//把不在缓冲（buffer）中的或者说是被释放出来的数据发送到浏览器。
    }

    /**
     * 获取进程总数
     * @param string $ps_flag
     * @return number
     * @author shenxin
     */
    protected function _get_process_num($ps_flag, \Closure $callback = null){
        if(empty($ps_flag)){
            throw new \Exception('process flag name empty!');
        }
        $ee = "ps -ef |grep -i  '$ps_flag' | grep -v 'grep' | wc -l";
        $ee1 = "ps -ef |grep -i  '$ps_flag' | grep -v 'grep'";
        $total_process =  `$ee`;
        $return_res = `$ee1`;
        echo '查询结果'.$return_res."\n";
        $total_process = (int)trim($total_process);
        $return = empty($total_process)?0:$total_process;
        if($callback instanceof  \Closure){
            return call_user_func($callback,$return,$ee);
        }
        return $return;
    }
}