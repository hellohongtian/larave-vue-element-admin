<?php
namespace App\Models\VideoVisa;

/**
 * 记录调试信息、错误信息的表
 */
class ErrorCodeLog extends VideoVisaModel
{

    protected $table='error_code_log';
    public $timestamps=false;

    public function runLog($errorCode, $message, $userId = null){
        $curDate = date('Y-m-d H:i:s');

        $url = 'zz test';
        $insertLogInfo = [
            'uid' => $userId === null ? -2 : $userId,
            'code' => $errorCode,
            'request' => json_encode(array_merge([$url, $_GET, $_POST])),
            'msg' => json_encode($message),
            'created_at' => $curDate,
            'updated_at' => $curDate,
        ];
        $this->insert($insertLogInfo);
    }

}