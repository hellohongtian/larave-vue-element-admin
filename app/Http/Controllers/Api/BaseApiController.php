<?php
namespace App\Http\Controllers\Api;

use App\Fast\FastGlobal;
use App\Http\Controllers\BaseController;
use App\Models\VideoVisa\ErrorCodeLog;
use Illuminate\Http\Request;

class BaseApiController extends BaseController
{

    const CODE_SUCCESS = 1;
    const CODE_FAIL = -1;
    const CODE_PARAMS = -2;

    const MSG_SUCCESS = '操作成功';
    const MSG_FAIL = '操作失败';
    const MSG_PARAMS = '参数传递错误';

    /**
     * 返回结果
     * @param int $code
     * @param string $message
     * @param array $data
     * @return mixed
     */
    public function showMsg($code = self::CODE_PARAMS, $message = self::MSG_PARAMS, $data = [])
    {
        $resData = [
            'code' => $code,
            'message' => $message,
            'data' => $data
        ];
//        if (FastGlobal::$retLog > 0) (new ErrorCodeLog())->runLog(config('errorLogCode.showMsg'),['request'=>Request::capture()->all(), 'ret'=>$resData],FastGlobal::$retLog);
        return response()->json($resData);
    }


}