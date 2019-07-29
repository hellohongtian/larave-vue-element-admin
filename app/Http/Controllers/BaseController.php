<?php
namespace App\Http\Controllers;

use App\Fast\FastGlobal;
use App\Models\VideoVisa\ErrorCodeLog;
use Illuminate\Http\Request;

class BaseController extends Controller
{

    const CODE_SUCCESS = 1;
    const CODE_FAIL = -1;
    const CODE_UPDATE = 2;
    const CODE_NO_ALERT = -2;

    const MSG_SUCCESS = '操作成功';
    const MSG_FAIL = '操作失败';
    const MSG_PARAMS = '参数传递错误';

    public $menudata = '';
    function __construct()
    {

    }

    /**
     * 返回结果
     * @param int $code
     * @param string $message
     * @param array $data
     * @param int $total
     * @return mixed
     */
    public function showMsg($code = self::CODE_FAIL, $message = self::MSG_PARAMS, $data = [],$total = 0)
    {
        $resData = [
            'code' => $code,
            'msg' => $message,
            'data' => $data
        ];
        if($total){
            $resData['count'] = $total;
        }
//        if (FastGlobal::$retLog > 0) (new ErrorCodeLog())->runLog(config('errorLogCode.showMsg'),['path'=>Request::capture()->path(),'request'=>Request::capture()->all(), 'ret'=>$resData],FastGlobal::$retLog);
        return response()->json($resData);
    }

    //用于关闭sso时显示的菜单
    public function createMenu($mentable){

    }

    /**
     * @note 分页返回格式
     * @param array $data
     * @param int $count
     * @param string $msg
     * @param int $code
     * @return mixed
     */
    protected function _page_format($data=[],$count=0,$msg='',$code=0){
        $rel['code']=$code;
        $rel['msg']=$msg;
        $rel['data']=$data;
        $rel['count'] = $count;
        return $rel;
    }
}

?>