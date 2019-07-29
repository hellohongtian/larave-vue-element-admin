<?php
namespace App\Http\Controllers\System;


use App\Http\Controllers\BaseController;
use App\Library\Helper;
use App\Library\RedisCommon;
use Illuminate\Http\Request;
use App\Models\VideoVisa\FastSystemConfig;
/**
 * 系统控制
 */
class SystemController extends BaseController
{

    private $env = '';

    public function __construct()
    {
        $this->env = Helper::isProduction() ? 'production' : '';;
    }

    public function index(Request $request){
        $config_obj = new FastSystemConfig();
        if($request->isMethod('post')){
            $redis_obj = RedisCommon::init();
            $redis_obj->delete(config('common.__maintained__'));
            $close_or_open = $request->input('close_or_open','');
            $maintained_start = $request->input('maintained_start','');
            $maintained_end = $request->input('maintained_end','');
            $desc = $request->input('desc','');
            $data = [
                'close_or_open' => $close_or_open,
                'maintained_start' => $maintained_start,
                'maintained_end' => $maintained_end,
                'desc' => $desc,
            ];
            if(strtotime($maintained_end) <= time()){
                return $this->showMsg(self::CODE_FAIL);
            }
            $res = $config_obj->updateBy(['value' => json_encode($data)],['key'=>'__maintained__']);
            if(!empty($close_or_open )){
                $redis_obj->setex(config('common.__maintained__'),$data);
            }
            return $this->showMsg(self::CODE_SUCCESS);
        }else{
            $res = $config_obj->getOne(['value'],['key'=>'__maintained__']);
            return view('system.index',['data' => json_decode($res['value'],true)]);
        }
    }
}