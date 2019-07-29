<?php
namespace App\Http\Controllers;

use App\Fast\FastKey;
use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Library\RedisCommon;
use App\Library\RedisObj;
use App\Models\VideoVisa\ErrorCodeLog;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\XinCredit\PersonCredit;
use App\Repositories\ApplyRepository;
use App\Repositories\CityRepository;
use App\Repositories\Netease\DownloadRepository;
use App\XinApi\CommonApi;
use App\XinApi\CreditApi;
use App\XinApi\ErpApi;
use App\XinApi\FinanceApi;
use Illuminate\Http\Request;
use App\Repositories\ImRepository;
use App\Repositories\Face\WzFace;
use App\Models\VideoVisa\VisaPool;
use DB;
use App\Models\VideoVisa\SeatManage;

/**
 * 手动调用接口
 */
class ManagerController extends BaseController {

    public $redis;
    public $neteaseRep;
    public $person_credit;

    public static  $redis_key_map = [
        'get',
        'set',
        'zRange',
        'zadd',
        'zRem',
        'delete'
    ];
    public function deal_redis(Request $request){
        $action = trim($request->input('action', ''));
        if (empty($action)) {
            return $this->showMsg(self::CODE_FAIL,self::MSG_PARAMS);
        }
        $this->redis = new RedisCommon();
        $key = trim($request->input('key', ''));
        $value = trim($request->input('value', ''));
        $score = trim($request->input('score', ''));
        switch ($action) {
            case 'get':
            case 'delete':
                $data = $this->redis->$action($key);
                break;
            case 'set':
                if (empty($value)) {
                    return $this->showMsg(self::CODE_FAIL,self::MSG_PARAMS);
                }
                $data = $this->redis->set($key,$value);
                break;
            case 'zRange':
                $data = $this->redis->$action($key,0,-1,true);
                break;
            case 'zadd':
                if (empty($value) || !isset($score)) {
                    return $this->showMsg(self::CODE_FAIL,self::MSG_PARAMS);
                }
                $data = $this->redis->$action($key,$score,$value);
                break;
            case 'zRem':
                if (empty($value)) {
                    return $this->showMsg(self::CODE_FAIL,self::MSG_PARAMS);
                }
                $data = $this->redis->$action($key,$value);
                break;
            default:
                return $this->showMsg(self::CODE_FAIL,self::MSG_PARAMS);
        }
        return $this->showMsg(self::CODE_SUCCESS,self::MSG_SUCCESS,$data);
    }
    /**
     * 清空指定坐席心跳
     * @param Request $request
     * @return bool
     */
    public function clearCache(Request $request)
    {
        $seatid = $request->input('seatid', 0);
        if($seatid){
            $seatObj = new SeatManage();
            $seatObj->updateKeepAliveKey($seatid);
            return $this->set_json(1,'坐席心跳绑定面签订单清除成功，坐席id->'.$seatid);
        }
        return $this->set_json();

    }

    public function set_json($code=0,$msg='',$data=[])
    {
        $resData = [
            'code' => -1,
            'msg' => '参数错误',
            'data' => []
        ];
        if(empty($code) || empty($msg)){
            return response()->json($resData);
        }
        $resData['code'] = $code;
        $resData['msg'] = $msg;
        if(!empty($data)){
            $resData['data'] = $data;
        }
        return response()->json($resData);
    }

    /**
     * 获取redis值
     * @param Request $request
     * @return mixed|string
     */
    public function get_redis(Request $request)
    {
        $params = $request->all();
        $key = !empty($params['key'])? $params['key']:'';
        $zset = !empty($params['zset'])? $params['zset']:0;
        $start = !empty($params['start'])? $params['start']:0;
        $end = !empty($params['end'])? $params['end']:1000;
        $func = !empty($params['func'])? $params['func']:'';
        $withscores = !empty($params['withscores'])? $params['withscores']:null;
        $redis = new RedisCommon();
        if(!empty($func)){
            $res = $redis->$func($key);
        }else{
            if($zset == 1){
                $res = $redis->zRange($key,$start,$end,$withscores);

            }else{
                $res= $redis->get($key);
            }
        }

        if(empty($res)){
            return '数据为空';
        }
        return print_r($res,true);
    }




}