<?php
namespace App\Http\Controllers\Netease;

use App\Fast\FastException;
use App\Http\Controllers\BaseController;
// use App\Models\VideoVisa\ImAccount;
// use App\Models\VideoVisa\SeatManage;
use App\Library\Common;
use App\Models\VideoVisa\ErrorCodeLog;
use App\Models\VideoVisa\NetEase\FastVideoCallBack;
use App\Models\VideoVisa\NetEase\FastVideoData;
use Illuminate\Http\Request;
use App\Models\VideoVisa\ReceiveInfo;

class NetDataSaveController extends BaseController {
	public function __construct() {
	}
	/**   事件类型
	1). "eventType"="1", 表示CONVERSATION消息，即会话类型的消息（目前包括P2P聊天消息，群组聊天消息，群组操作，好友操作）

	2). "eventType"="2", 表示LOGIN消息，即用户登录事件的消息

	3). "eventType"="3", 表示LOGOUT消息，即用户登出事件的消息

	4). "eventType"="4", 表示CHATROOM消息，即聊天室中聊天的消息

	5). "eventType"="5", 表示AUDIO/VEDIO/DataTunnel消息，即汇报实时音视频通话时长、白板事件时长的消息

	6). "eventType"="6", 表示音视频/白板文件存储信息，即汇报音视频/白板文件的大小、下载地址等消息

	7). "eventType"="7", 表示单聊消息撤回抄送

	8). "eventType"="8", 表示群聊消息撤回抄送

	9). "eventType"="9", 表示CHATROOM_INOUT信息，即汇报主播或管理员进出聊天室事件消息

	10). "eventType"="10", 表示ECP_CALLBACK信息，即汇报专线电话通话结束回调抄送的消息

	11). "eventType"="11", 表示SMS_CALLBACK信息，即汇报短信回执抄送的消息

	12). "eventType"="12", 表示SMS_REPLY信息，即汇报短信上行消息

	13). "eventType"="13", 表示AVROOM_INOUT信息，即汇报用户进出音视频房间的消息

	14). "eventType"="14", 表示CHATROOM_QUEUE_OPERATE信息，即汇报聊天室队列操作的事件消息

	数据格式：{"eventType":"6","fileinfo":"[{\"caller\":true,\"channelid\":\"6290737000999815988\",\"filename\":\"xxxxxx.type\",\"md5\":\"a9b248e29669d588dd0b10259dedea1a\",\"mix\":false,\"size\":\"2167\",\"type\":\"gz\",\"vid\":\"1062591\",\"url\":\"http://xxxxxxxxxxxxxxxxxxxx.type\",\"user\":\"zhangsan\"}]"}
	**/

	public function videoCallback(Request $request)
	{
		try {
			//获取到抄送的body信息
			$params = file_get_contents("php://input");
			(new ErrorCodeLog())->runLog(config('errorLogCode.videoCallback') , $params);
			if (empty($params)) {
				return $this->showMsg(self::CODE_FAIL, self::MSG_FAIL);
			}
			$paramList = json_decode($params, true);
			//保存所有抄送信息的字段
			$eventType = isset($paramList['eventType']) ? $paramList['eventType'] : 90;

			$res = $this->saveCallBackVideoInfo($eventType, $paramList);
			return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
		} catch (\Exception $e) {
			(new ErrorCodeLog())->runLog(config('errorLogCode.videoCallback') , ['message' => $e->getMessage()]);
			Common::sendMail('视频回调报错', $e->getMessage());
			return $this->showMsg(self::CODE_FAIL, self::MSG_FAIL);
		}

	}

	//视频抄送结果处理
	public function saveCallBackVideoInfo($eventType, $sourceParam){
		if ($eventType == 6) {
			$fileInfo = json_decode($sourceParam['fileinfo'],true);
			$channelId = isset($fileInfo[0]['channelid']) ? $fileInfo[0]['channelid'] : '';
		} elseif ($eventType == 5) {
			$channelId = isset($sourceParam['channelId']) ? $sourceParam['channelId'] : '';
		} else {
			if (isset($sourceParam['channelId']))
				$channelId = $sourceParam['channelId'];
			elseif (isset($sourceParam['channelid']))
				$channelId = $sourceParam['channelid'];
			else
				$channelId = '';
		}
		

		$insertData = [
			'event_type' => $eventType,
			'channel_id' => $channelId,
			'sources' => json_encode($sourceParam),
			'create_time' => time(),
		];

		try {
			return (new FastVideoCallBack())->insert($insertData);
		} catch (\Exception $e) {
			$logData = ['request'=>Request::capture()->all(), 'data'=>$insertData, 'msg' => $e->getMessage()];
			FastException::throwException(json_encode($logData), 1);
			return 0;
		}
	}
    //腾讯回调地址
	public function videoTecentCallback(Request $request)
	{
		try {
			//获取到抄送的body信息
			$params = file_get_contents("php://input");
//            $params = "{\"appid\":1255758297,\"channel_id\":\"25000_mix_25000_851490aa97429810afd6daa86ed8c1a2_2_1555040787\",\"duration\":434,\"end_time\":1555045086,\"end_time_usec\":502573,\"event_type\":100,\"file_format\":\"mp4\",\"file_id\":\"5285890787860197450\",\"file_size\":69244605,\"media_start_time\":1135,\"record_bps\":0,\"record_file_id\":\"5285890787860197450\",\"sign\":\"90f10e48151d894e489b4aae18e8826d\",\"start_time\":1555044650,\"start_time_usec\":242675,\"stream_id\":\"25000_mix_25000_851490aa97429810afd6daa86ed8c1a2_2_1555040787\",\"stream_param\":\"\",\"t\":1555045696,\"task_id\":\"16015961745781842325\",\"video_id\":\"1255758297_4efddd1ce4dc45ccb703b692c728d207\",\"video_url\":\"http:\/\/1255758297.vod2.myqcloud.com\/3e017995vodcq1255758297\/87d4ca785285890787860197450\/f0.mp4\"}\n";
            (new ErrorCodeLog())->runLog(config('errorLogCode.testVideoCallback') , $params);
			if (empty($params)) {
				return $this->showMsg(self::CODE_FAIL, self::MSG_FAIL);
			}
			$paramList = json_decode($params, true);
			//保存所有抄送信息的字段
			$eventType = $paramList['event_type'];
			if(empty($eventType)) return;

			$res = $this->saveTecentCallBackVideoInfo($eventType, $paramList);
			return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
		} catch (\Exception $e) {
			(new ErrorCodeLog())->runLog(config('errorLogCode.videoCallback') , ['message' => $e->getMessage()]);
			Common::sendMail('视频回调报错', $e->getMessage());
			return $this->showMsg(self::CODE_FAIL, self::MSG_FAIL);
		}

	}

    //生产环境回调测试环境地址
    public function testVideoTecentCallback(Request $request)
    {
        try {
            //获取到抄送的body信息
            $params = $request->input('source');
//            $params = "{\"appid\":1255758297,\"channel_id\":\"25000_mix_25000_851490aa97429810afd6daa86ed8c1a2_2_1555040787\",\"duration\":434,\"end_time\":1555045086,\"end_time_usec\":502573,\"event_type\":100,\"file_format\":\"mp4\",\"file_id\":\"5285890787860197450\",\"file_size\":69244605,\"media_start_time\":1135,\"record_bps\":0,\"record_file_id\":\"5285890787860197450\",\"sign\":\"90f10e48151d894e489b4aae18e8826d\",\"start_time\":1555044650,\"start_time_usec\":242675,\"stream_id\":\"25000_mix_25000_851490aa97429810afd6daa86ed8c1a2_2_1555040787\",\"stream_param\":\"\",\"t\":1555045696,\"task_id\":\"16015961745781842325\",\"video_id\":\"1255758297_4efddd1ce4dc45ccb703b692c728d207\",\"video_url\":\"http:\/\/1255758297.vod2.myqcloud.com\/3e017995vodcq1255758297\/87d4ca785285890787860197450\/f0.mp4\"}\n";
            (new ErrorCodeLog())->runLog(config('errorLogCode.videoCallback') , $params);
            if (empty($params)) {
                return $this->showMsg(self::CODE_FAIL, self::MSG_FAIL);
            }
            $paramList = json_decode($params, true);
            //保存所有抄送信息的字段
            $eventType = $paramList['event_type'];
            if(empty($eventType)) return;

            $res = $this->saveTecentCallBackVideoInfo($eventType, $paramList);
            return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
        } catch (\Exception $e) {
            (new ErrorCodeLog())->runLog(config('errorLogCode.videoCallback') , ['message' => $e->getMessage()]);
            Common::sendMail('视频回调报错', $e->getMessage());
            return $this->showMsg(self::CODE_FAIL, self::MSG_FAIL);
        }

    }

	//视频抄送结果处理
	public function saveTecentCallBackVideoInfo($eventType, $sourceParam){

		if ($eventType != 100) {//event_type0 — 断流； 1 — 推流；100 — 新的录制文件已生成；200 — 新的截图文件已生成。
            return;
		}
		$channelId = $sourceParam['channel_id'];
		$insertData = [
			'event_type' => $eventType,
			'channel_id' => $channelId,
			'sources' => json_encode($sourceParam),
			'create_time' => time(),
		];
		try {
			return (new FastVideoCallBack())->insert($insertData);
		} catch (\Exception $e) {
			$logData = ['request'=>Request::capture()->all(), 'data'=>$insertData, 'msg' => $e->getMessage()];
			FastException::throwException(json_encode($logData), 1);
			return 0;
		}
	}

}