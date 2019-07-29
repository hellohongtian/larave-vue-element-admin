<?php
namespace App\Http\Controllers\AjaxFail;

use App\Http\Controllers\BaseController;
use App\Models\VideoVisa\ErrorCodeLog;
use Illuminate\Http\Request;
use App\Library\Helper;
use App\Library\Common;

class AjaxFailController extends BaseController
{
    public function saveLog(Request $request)
    {
        $params = $request->all();

        $errorCode = $params['errorCode'];

        $title = Helper::isProduction() ? '中央面签API报错' : '[测试]中央面签API报错';
        Common::sendMail($title, '错误信息: ' . print_r($params, true), config('mail.developer'));

        (new ErrorCodeLog())->runLog($errorCode, $params);
    }

}