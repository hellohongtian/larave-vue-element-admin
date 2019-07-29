<?php

namespace App\Repositories\Async;

use App\Fast\FastKey;
use App\Library\RedisCommon;
use App\Models\VideoVisa\ErrorCodeLog;
use App\Models\VideoVisa\Log\LogRequestOut;
use App\Models\VideoVisa\Log\LogSeatOperation;
use App\Models\VideoVisa\LogRequestFromChaojibao;
use App\Models\VideoVisa\LogRequestFromErp;
use App\Repositories\BaseRepository;

class AsyncInsertRepository extends BaseRepository{

    private $redisObj;

    public function __construct()
    {
        $this->redisObj = new RedisCommon();
    }

    public function pushErpLog($applyId, $uri, $result)
    {
        $request = array_merge($_GET, $_POST);

        $logData = [
            'uri' => trim($uri,'/'),
            'request' => json_encode($request),
            'body' => json_encode($result),
            'created_at' => date('Y-m-d H:i:s'),
            'apply_id' => $applyId,
        ];

        $this->redisObj->qset(FastKey::LOG_QUEUE_ERP, $logData);
    }

    public function popErpLog($count = 100)
    {
        $logDataList = $this->redisObj->qget(FastKey::LOG_QUEUE_ERP, $count);
        $log = [];
        foreach ($logDataList as $tempLogData) {
            $log[] = [
                'uri' => isset($tempLogData['uri']) ? ($tempLogData['uri']) : '',
                'request' => isset($tempLogData['request']) ? ($tempLogData['request']) : '',
                'body' => isset($tempLogData['body']) ? ($tempLogData['body']) : '',
                'created_at' => isset($tempLogData['created_at']) ? ($tempLogData['created_at']) : '',
                'apply_id' => isset($tempLogData['apply_id']) ? ($tempLogData['apply_id']) : '',
            ];
        }
        (new LogRequestFromErp())->insert($log);
    }

    public function pushCjbLog($masterId, $uri, $result)
    {
        $request = array_merge($_GET, $_POST);

        $logData = [
            'uri' => trim($uri,'/'),
            'request' => json_encode($request),
            'body' => json_encode($result),
            'created_at' => date('Y-m-d H:i:s'),
            'master_id' => $masterId,
        ];

        $this->redisObj->qset(FastKey::LOG_QUEUE_CJB, $logData);
    }

    public function popCjbLog($count = 100)
    {
        $logDataList = $this->redisObj->qget(FastKey::LOG_QUEUE_CJB, $count);
        $log = [];
        foreach ($logDataList as $tempLogData) {
            $log[] = [
                'uri' => isset($tempLogData['uri']) ? ($tempLogData['uri']) : '',
                'request' => isset($tempLogData['request']) ? ($tempLogData['request']) : '',
                'body' => isset($tempLogData['body']) ? ($tempLogData['body']) : '',
                'created_at' => isset($tempLogData['created_at']) ? $tempLogData['created_at'] : '',
                'master_id' => isset($tempLogData['master_id']) ? $tempLogData['master_id'] : '',
            ];
        }
        (new LogRequestFromChaojibao())->insert($log);
    }

    public function pushOperationLog($seatId, $uri)
    {
        $request = array_merge($_GET, $_POST);
        $uri = trim(strtolower($uri),'/');
        if($uri == 'login/onlogin') {
            unset($request['password']);
        }
        $logData = [
            'seat_id' => $seatId,
            'uri' => $uri,
            'request' => json_encode($request),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->redisObj->qset(FastKey::LOG_QUEUE_OPERATION, $logData);
    }

    public function popOperationLog($count = 100)
    {
        $logDataList = $this->redisObj->qget(FastKey::LOG_QUEUE_OPERATION, $count);
        $log = [];

        foreach ($logDataList as $tempLogData) {
            $log[] = [
                'uri' => isset($tempLogData['uri']) ? $tempLogData['uri'] : '',
                'request' => isset($tempLogData['request']) ? $tempLogData['request'] : '',
                'created_at' => isset($tempLogData['created_at']) ? $tempLogData['created_at'] : '',
                'seat_id' => isset($tempLogData['seat_id']) ? $tempLogData['seat_id'] : '',
            ];
        }
        (new LogSeatOperation())->insert($log);
    }

    public function pushRemoteRequestLog($remoteRequestLog)
    {
        $this->redisObj->qset(FastKey::LOG_QUEUE_REMOTE_REQUEST, $remoteRequestLog);
    }

    public function popRemoteRequestLog($count = 100)
    {
        $logDataList = $this->redisObj->qget(FastKey::LOG_QUEUE_REMOTE_REQUEST, $count);
        $log = [];

        foreach ($logDataList as $tempLogData) {
            $tempLog = [
                'uri' => isset($tempLogData['uri']) ? $tempLogData['uri'] : '',
                'http_code' => isset($tempLogData['http_code']) ? $tempLogData['http_code'] : '',
                'http_method' => isset($tempLogData['http_method']) ? $tempLogData['http_method'] : '',
                'request' => isset($tempLogData['request']) ? $tempLogData['request'] : '',
                'body' => isset($tempLogData['body']) ? $tempLogData['body'] : '',
                'created_at' => isset($tempLogData['created_at']) ? $tempLogData['created_at'] : '',
            ];
            $stime = explode(' ', $tempLogData['stime']);
            $etime = explode(' ', $tempLogData['etime']);
            $latency = $etime[0] + $etime[1] - $stime[0] - $stime[1];
            $latency = intval($latency * 1000000);
            $tempLog['latency'] = $latency;
            $tempLog['stime'] = date('Y-m-d H:i:s.', $stime[1]) . intval($stime[0] * 1000000);
            $tempLog['etime'] = date('Y-m-d H:i:s.', $etime[1]) . intval($etime[0] * 1000000);

            $log[] = $tempLog;
        }
        (new LogRequestOut())->insert($log);
    }
}