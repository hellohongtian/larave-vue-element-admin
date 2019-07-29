<?php
namespace App\Console\Commands;

use App\Library\Common;
use App\Models\VideoVisa\ErrorCodeLog;
use Illuminate\Console\Command;
use App\Repositories\Cron\ApplyQuqeRepository;

/**
 * 排队逻辑相关
 * Class ApplyQuqeCron
 * @package App\Console\Commands
 */
class ApplyQuqeCron extends BaseCommand
{
    protected $signature = 'applyQueue {func}';
    protected $description = '排队逻辑';
    protected $queueRepos;

    public function __construct()
    {
        parent::__construct();
        $this->queueRepos = new ApplyQuqeRepository();
    }

    public function handle()
    {
        $func = $this->argument('func');
        $this->{$func}();
    }

    //将发起排队的订单，转换为可领取 status:2转换成9
    public function manageQueue(){
        $startTime = time();
        $logData = [];

        do {
            try {
                if (!$this->isCommandRunning('applyQueue', 'autoAllotApply')) {
                    $logData[] = $this->queueRepos->manageQueue();
                }
            } catch (\Exception $e) {
                @Common::sendMail('排队订单转为可领取订单脚本报错', json_encode($logData), config('mail.developer'));
            }
            sleep(2);
            $endTime = time();
        } while ($endTime - $startTime < 57);

        (new ErrorCodeLog())->runLog(config('errorLogCode.manageQueue'), $logData);
    }

    //将排队中的订单，自动分配给坐席
    public function autoAllotApply(){
        $logData = $this->queueRepos->autoAllotApply();
        (new ErrorCodeLog())->runLog(config('errorLogCode.autoAllotApply'), $logData);
    }

    //凌晨清空队列
    public function clearQueue(){
        $this->queueRepos->clearQueue();
    }
}