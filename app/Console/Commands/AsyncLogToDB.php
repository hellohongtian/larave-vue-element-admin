<?php

namespace App\Console\Commands;

use App\Repositories\Async\AsyncInsertRepository;
use App\Models\VideoVisa\LogRequestFromChaojibao;
use App\Models\VideoVisa\LogRequestFromErp;
use App\Library\RedisCommon;


class AsyncLogToDB extends BaseCommand
{
    protected $signature = 'AsyncLogToDB {func}';
    protected $description = '异步记录日志到数据库';
    protected $seat;
    protected $redis;

    public function __construct()
    {
        parent::__construct();
        $this->redis = new RedisCommon();
    }

    public function handle()
    {
        $func = $this->argument('func');
        $this->{$func}();
    }

    /**
     * api请求log
     */
    public function dumpApiRequestLog()
    {
        $erpNeedFields = ['uri','request','body','stime','etime','created_at','apply_id'];
        $cjbNeedFields = ['uri','request','body','stime','etime','created_at','master_id'];

        $this->apiLogToDb(LogRequestFromErp::LOG_QUEUE, $erpNeedFields, 'LogRequestFromErp');
        $this->apiLogToDb(LogRequestFromChaojibao::LOG_QUEUE, $cjbNeedFields, 'LogRequestFromChaojibao');
    }

    /**
     * api请求log redis队列日志入库
     */
    private function apiLogToDb($queueName,$needFields,$modelName)
    {

        do {
            $lines = $this->redis->qget($queueName, 500);

            if (empty($lines)) {
                break;
            }
            $logs = [];
            foreach ($lines as $log) {
                if (!is_array($log)) { // 脏数据
                    continue;
                }
                //log必须包含$fields字段
                $diff = array_diff($needFields, array_keys($log));
                if (!empty($diff)) { // 缺少字段，脏数据
                    continue;
                }
                //计算耗时
                $stime = explode(' ', $log['stime']);
                $etime = explode(' ', $log['etime']);
                $latency = $etime[0] + $etime[1] - $stime[0] - $stime[1];
                $latency = intval($latency * 1000000);
                $log['latency'] = $latency;
                $log['stime'] = date('Y-m-d H:i:s.', $stime[1]) . intval($stime[0] * 1000000);
                $log['etime'] = date('Y-m-d H:i:s.', $etime[1]) . intval($etime[0] * 1000000);
                $logs[] = $log;
            }
            if (!empty($logs)) {
                if($modelName == 'LogRequestFromChaojibao'){
                    $modelObj = new LogRequestFromChaojibao(); //bug
                }elseif($modelName == 'LogRequestFromErp') {
                    $modelObj = new LogRequestFromErp();
                }else{
                    continue;
                }

                $rs = $modelObj->insert($logs);
                sleep(1);
            }
        } while (true);

        echo '执行完毕';
    }

    /**
     * 异步记录来自超级宝的请求日志
     */
    public function dumpRequestFromChaojibaoLog()
    {
        $i = 0;
        do {
            (new AsyncInsertRepository())->popCjbLog();
            sleep(1);
        } while (++$i < 10);
    }

    /**
     * 异步记录来自ERP的请求日志
     */
    public function dumpRequestFromErpLog()
    {
        $i = 0;
        do {
            (new AsyncInsertRepository())->popErpLog();
            sleep(1);
        } while (++$i < 10);
    }

    /**
     * 异步记录坐席操作日志
     */
    public function dumpSeatOperationLog()
    {
        $i = 0;
        do {
            (new AsyncInsertRepository())->popOperationLog();
            sleep(1);
        } while (++$i < 10);
    }

    /**
     * 异步记录发起的第三方请求日志
     */
    public function dumpRequestOutLog()
    {
        $i = 0;
        do {
            (new AsyncInsertRepository())->popRemoteRequestLog();
            sleep(1);
        } while (++$i < 10);
    }
}