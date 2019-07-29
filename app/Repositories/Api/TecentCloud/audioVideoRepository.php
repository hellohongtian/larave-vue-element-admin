<?php
namespace App\Repositories\Api\TecentCloud;
use App\Library\HttpRequest;
use App\Repositories\WebRTCSigApi;
use App\Models\VideoVisa\NetEase\FastVideoCallBack;

class audioVideoRepository
{

    public $fastVideoCallbackModel;
    public function __construct() {
        $this->fastVideoCallbackModel = new FastVideoCallBack();
    }
    //获取sdkappid , accountType
    public function getKeysByAppName($appName) {

        $appKeys = config("tecentCloudConfig.appName.".$appName);
        unset($appKeys['privateKey']);
        unset($appKeys['publicKey']);

        if(!empty($appKeys)) {
            return ['code' => 1, 'msg' => '操作成功', 'data' => json_encode($appKeys)];
        }
        else {
            return ['code' => -1, 'msg' => '获取失败'];
        }
    }
    //获取签名
    public function getUserSig($appName, $userId, $expire=3600) {

        if(empty($expire)) {
            $expire = 3600;
        }

        $webRTC = new WebRTCSigApi($appName);
        $userSig = $webRTC->genUserSig($userId, $expire);
        return ['code' => 1, 'msg' => '操作成功', 'data' => $userSig];
    }
    //获取视频地址
    public function getRecordVideo($channelId) {

        $videoCallbackData = $this->fastVideoCallbackModel->getSourcesByChannelId($channelId);
        return ['code' => 1, 'msg' => '操作成功', 'data' => $videoCallbackData];
    }

    private function getMixLiveCode($fromLiveCode, $toLiveCode) {

        $appid = config("tecentCloudConfig.appid");
        $apiKey = config("tecentCloudConfig.apiKey");
        $t = time() + 600;
        $sign = md5($apiKey.$t);
        $url = "http://fcgi.video.qcloud.com/common_access?appid="; 
        $url = $url.$appid."&interface=Mix_StreamV2&t=".$t."&sign=".$sign;

        $mixLiveCode = "mix_".$toLiveCode."_2_".$t;
        $params = array(
            "timestamp" => $t,
            "eventId" => $t,
            "interface" => array(
                "interfaceName" => "Mix_StreamV2",
                "para" => array(
                    "app_id" => $appid,
                    "interface" => "mix_streamv2.start_mix_stream_advanced",
                    "mix_stream_session_id" => $mixLiveCode,
                    "output_stream_id" => $mixLiveCode,
                    "output_stream_type" => 1,
                    "input_stream_list" => array(
                        array(
                            "input_stream_id" => $toLiveCode,
                            "layout_params" => array(
                                "image_layer" => 1,
                            )
                        ),
                        array(
                            "input_stream_id" => $fromLiveCode,
                            "layout_params" => array(
                                "image_layer" => 2
                            )
                        )
                    )
                )
            )
        );

        $retInfo = array(
            'code' => 1,
            'mixLiveCode' => $mixLiveCode,
            'msg' => '',
        );

        $ret = HttpRequest::doParamPostJson($url, ['json' => $params]);
        if(empty($ret) || $ret['code'] != 0) {
            $retInfo['code'] = -1;
            $retInfo['msg'] = json_encode($ret);
        }

        return $retInfo;
    }
    //其他系统调用,混流
    public function applyMixStream($userOneId, $userTwoId, $roomId) {

        $bizid = config("tecentCloudConfig.bizid");
        $fromLiveCode = $bizid."_".md5($roomId."_".$userOneId."_main");
        $toLiveCode = $bizid."_".md5($roomId."_".$userTwoId."_main");

        $ret = $this->getMixLiveCode($fromLiveCode, $toLiveCode);
        if($ret['code'] == 1) {
            return ['code' => 1, 'msg' => '操作成功', 'data' => $ret['mixLiveCode']];
        }
        else {
            return ['code' => -1, 'msg' => $ret['msg']];
        }
    }
}



