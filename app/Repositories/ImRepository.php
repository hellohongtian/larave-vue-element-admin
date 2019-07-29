<?php
namespace App\Repositories;

use App\Library\RedisCommon;
use App\Library\Common;
use App\Library\ElkLog;
use App\Models\VideoVisa\VideoVisaLog;

class ImRepository
{

    //封禁网易云通信ID
    public function blockUser($accid = ''){
        $ret = [
            'code' => -1,
            'data' => []
        ];
        if(!$accid){
            $ret['msg'] = '缺少参数！';
            return $ret;
        }

        $curl_params= array(
            'accid' => $accid,
        );
        $ret = $this->imCurl('block_user', $curl_params);
        return $ret;
    }

    //解禁网易云通信ID
    public function unblockUser($accid = ''){
        $ret = [
            'code' => -1,
            'data' => []
        ];
        if(!$accid){
            $ret['msg'] = '缺少参数！';
            return $ret;
        }

        $curl_params= array(
            'accid' => $accid,
        );
        $ret = $this->imCurl('unblock_user', $curl_params);
        return $ret;
    }

    //更新并获取新token
    public function refreshToken($accid = ''){
        $ret = [
            'code' => -1,
            'data' => []
        ];
        if(!$accid){
            $ret['msg'] = '缺少参数！';
            return $ret;
        }

        $curl_params= array(
            'accid' => $accid,
        );
        $refersh_token = $this->imCurl('refreshToken', $curl_params);
        return $refersh_token;
    }

    //获取名片列表
    public function getUinfos($accids = []){
        $ret = [
            'code' => -1,
            'data' => []
        ];
        if(!$accids){
            $ret['msg'] = '缺少参数！';
            return $ret;
        }

        $data= array(
            'accids' => json_encode($accids)
        );
        $userInfo = $this->imCurl('getUserInfo', $data);
        return $userInfo;
    }

    //创建网易云通信ID
    public function createUser($params = []){
        $ret = [
            'code' => -1,
            'data' => []
        ];
        if(!$params){
            $ret['msg'] = '缺少参数！';
            return $ret;
        }

        if(!isset($params['accid'])){
            $ret['msg'] = '缺少用户名！';
            return $ret;
        }
        $curl_params= array(
            'accid' => $params['accid'],
            'name'  => isset($params['name'])?$params['name']:'',
            'gender'  => isset($params['gender'])?$params['gender']:0,
            'props' => isset($params['props'])?$params['props']:'{}',
            'icon'  => isset($params['icon'])?$params['icon']:'',
            'token' => isset($params['token'])?$params['token']:''
        );

        $create_user = $this->imCurl('create_user', $curl_params);
        return $create_user;
    }


    //更新网易账号信息
    public function updateUser($params = []){
        $ret = [
            'code' => -1,
            'data' => []
        ];
        if(!$params){
            $ret['msg'] = '缺少参数！';
            return $ret;
        }

        if(!isset($params['accid'])){
            $ret['msg'] = '缺少用户名！';
            return $ret;
        }
        $curl_params= array(
            'accid' => $params['accid'],
        );
        if(isset($params['name'])){
            $curl_params['name'] = $params['name'];
        }
        if(isset($params['gender'])){
            $curl_params['gender'] = $params['gender'];
        }

        $update_user = $this->imCurl('update_user', $curl_params);
        return $update_user;
    }


    //网易通信 post
    public function imCurl($action = '', $data = []){
        $ret = [
            'code' => -1,
            'data' => []
        ];
        if(!$action || !$data){
            $ret['msg'] = '缺少参数！';
            return $ret;
        }
        $im_header = $this->getImHeader();
        if($im_header['code'] != 1){
            return $im_header['msg'];
        }

        //获取URL
        $url = $this->getActionUrl($action);
        $http_header = array(
            'AppKey:'.$im_header['AppKey'],
            'Nonce:'.$im_header['Nonce'],
            'CurTime:'.$im_header['CurTime'],
            'CheckSum:'.$im_header['CheckSum'],
            'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
        );

        $postdataArray = array();
        foreach ($data as $key=>$value){
            array_push($postdataArray, $key.'='.urlencode($value));
        }
        $postdata = implode('&', $postdataArray);

        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_POST, 1);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt ($ch, CURLOPT_HEADER, false );
        curl_setopt ($ch, CURLOPT_HTTPHEADER,$http_header);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER,false); //处理http证书问题
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        if (false === $result) {
            $result =  curl_errno($ch);
        }
        curl_close($ch);
        $result = json_decode($result, true);
        //记录日志
        $log = [
            'action' => $action,
            'params' => $data,
            'result' => $result,
            'time' => time()
        ];
        ElkLog::writeLog($log);
        $log_model = new VideoVisaLog();
        if($result['code'] != 200){
            //记录log
            $log_model->writeLog(1, $log);
            $ret['msg'] = $result['desc'];
            return $ret;
        }
        $ret['code'] = 1;
        $ret['msg'] = '成功';
        $ret['data'] = $result;
        return $ret;
    }

    //根据动作类型获取地址
    private function getActionUrl($action = ''){
        if(!$action){
            return false;
        }
        $url = config('imconfig.actionUrl');
        return isset($url[$action])?$url[$action]:false;
    }

    //获取网易IM头信息
    public function getImHeader(){

        try{
            $redis_key = config('imconfig.imCheckSumRedisKey');
            $redis = new RedisCommon;
            $im_header = $redis->get($redis_key);
            if(!$im_header){
                $common = new Common();
                $nonce = $common->rand_str(128);
                $curTime = (string)(time());	//当前时间戳，以秒为单位
                $appKey = config('imconfig.AppKey');
                $AppSecret = config('imconfig.AppSecret');
                $join_string = $AppSecret.$nonce.$curTime;
                $check_sum = sha1($join_string);

                $im_header = [
                    'AppKey' => $appKey,
                    'Nonce' => $nonce,
                    'CurTime' => $curTime,
                    'CheckSum' => $check_sum,
                ];
                //缓存4分钟
                $redis->setex($redis_key, $im_header, 240);
                $im_header['code'] = 1;
            } else {
                $im_header['code'] = 1;
            }
            return $im_header;
        } catch (\Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }

    }


}
