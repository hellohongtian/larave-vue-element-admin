<?php
namespace App\Http\Controllers\Netease;

use App\Fast\FastException;
use App\Fast\FastGlobal;
use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Models\VideoVisa\ErrorCodeLog;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\ImAccount;
use App\Models\VideoVisa\NetEase\FastVideoData;
use App\Models\VideoVisa\SeatManage;
use Illuminate\Http\Request;
use App\Models\VideoVisa\ReceiveInfo;
use App\Models\VideoVisa\VisaRemark;
use App\Repositories\Face\WzFace;
use App\Models\VideoVisa\VisaPool;
use App\Repositories\WebRTCSigApi;
use App\Library\HttpRequest;

class ImController extends BaseController {
	public $seatManageModel;
	public $imAccountModel;
	public $videoModel;
	public function __construct() {
		$this->seatManageModel = new SeatManage();
		$this->imAccountModel = new ImAccount();
		$this->videoModel = new ReceiveInfo();
	}

	public function getuinfo() {

		$seat_id = session('uinfo.seat_id');
		//通过seat_id 获取器im account 相关信息
		$account_id = $this->seatManageModel->select("im_account_id")->where(['id' => $seat_id])->first();
		if (!$account_id) {
			return json_encode(['code' => -1, 'msg' => '当前坐席未在网易注册1']);
		}
		$account_id = $account_id->toArray();
		$account_info = $this->imAccountModel->select('accid as account_id', 'nickname', 'token')->where(['id' => $account_id['im_account_id']])->first()->toArray();
		if (empty($account_info)) {
			return json_encode(['code' => -1, 'msg' => '当前坐席未在网易注册2']);
		}
		$account_info['AppKey'] = config('imconfig.AppKey');
		$account_info['AppSecret'] = config('imconfig.AppSecret');
		return json_encode(['code' => 1, 'msg' => 'success', 'data' => $account_info]);
	}

	public function getTecentInfo() {

		$seat_id = session('uinfo.seat_id');
		//通过seat_id 获取器im account 相关信息
		$account_id = $this->seatManageModel->select("im_account_id")->where(['id' => $seat_id])->first();
		if (!$account_id) {
			return json_encode(['code' => -1, 'msg' => '当前坐席未在网易注册1']);
		}
		$account_id = $account_id->toArray();
		$account_info = $this->imAccountModel->select('accid as account_id', 'nickname', 'token')->where(['id' => $account_id['im_account_id']])->first()->toArray();
		if (empty($account_info)) {
			return json_encode(['code' => -1, 'msg' => '当前坐席未在网易注册2']);
		}
		$account_info['sdk_appid'] = config('tecentCloudConfig.appName.fast.sdkappid');
		$account_info['account_type'] = config('tecentCloudConfig.appName.fast.accountType');
		$account_info['user_sig'] = (new WebRTCSigApi('fast'))->genUserSig($account_info['account_id']);
		return json_encode(['code' => 1, 'msg' => 'success', 'data' => $account_info]);
	}

	//项目紧急，暂时不用于对外提供接口
	private function getMixLiveCode($fromLiveCode, $toLiveCode, $visaId ,$count=0) {

		$appid = config("tecentCloudConfig.appid");
		$apiKey = config("tecentCloudConfig.apiKey");
		$bizid = config("tecentCloudConfig.bizid");
		$t = time() + 600;
		$sign = md5($apiKey.$t);
		$url = "http://fcgi.video.qcloud.com/common_access?appid="; 
		$url = $url.$appid."&interface=Mix_StreamV2&t=".$t."&sign=".$sign;
//		$mixLiveCode = $bizid."_mix_".$toLiveCode."_2_".$t;//老的
        $tips = $visaId.'_';
        if(is_production_env()){
            $tips .= 1;
        }else{
            $tips .= 2;
        }
		$mixLiveCode = $bizid."_mix_".$toLiveCode."_2_".$tips;//新的

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
						array(//背景画面
							"input_stream_id" => $toLiveCode,
							"layout_params" => array(
								"image_layer" => 1,
							)
						),
						array(//小画面
							"input_stream_id" => $fromLiveCode,
							"layout_params" => array(
								"image_layer" => 2
							)
						)
					)
				)
			)
		);
		$res = HttpRequest::doParamPostJson($url, ['json' => $params]);
        if(isset($res['code']) && $res['code'] == 0){
            if($count >1 && $count <= 10){
                Common::sendMail('重推混流成功!id='.$visaId,$res);
            }
            //不论混流请求是否成功，都返回混流的结果
            return $mixLiveCode;
        }else{
            if($count == 10){
                Common::sendMail('混流失败!id='.$visaId,$res);
            }
            return '';
        }

	}

//	视频录制 发送混流
	public function notifyMixRecord(Request $request) {
		
		$fromUserId = $request->input('fromUserId', '');
		$toUserId = $request->input('toUserId', '');
		$roomId = $request->input('roomId', '');
		$dataType = $request->input('dataType', 'main');
		$visaId = $request->input('visaId', 0);
		$count = $request->input('count', 0);
		$bizid = config("tecentCloudConfig.bizid");

		if(empty($fromUserId) || empty($toUserId) || empty($roomId) || empty($bizid) || empty($visaId) || empty($count)) {
			return json_encode(['code'=>0,'msg'=>'notifyMixRecord参数错误']);
		}

		//计算fromUserId与toUserId的直播码
		$fromLiveCode = $bizid."_".md5($roomId."_".$fromUserId."_".$dataType);
		$toLiveCode = $bizid."_".md5($roomId."_".$toUserId."_".$dataType);

		//发送混流请求,即使生成混流失败，也返回成功，不影响业务视频审核
		$mixLiveCode = $this->getMixLiveCode($fromLiveCode, $toLiveCode, $visaId,$count);

        if($mixLiveCode){
            $videoObj = new FastVideoData();
            $order_id = 1;
            $videoCount = $videoObj->countBy(['visa_id' => $visaId]);
            if($videoCount){
                $order_id = $videoCount+1;
            }
            //查询result表数据,插入一条数据到fast_visa_data表,记录channel_id
            $videoObj->insert(['visa_id' => $visaId,'channel_id'=>$mixLiveCode,'order_id' =>$order_id]);
        }

		//返回混流后的直播流
		return json_encode(['code'=>1,'msg'=>'操作成功', 'data'=>$mixLiveCode]);
	}

	public function dowanLoad(Request $request){
		$pathToFile = $request->input('fileurl','');
		if (empty($pathToFile)) {
			return false;
		}
		return response()->download($pathToFile);
	}
	/**
	 * 点击视频面签进行状态记录   
	 * @param  [applyid]
	 * @param  [visa_status]
     * 视屏面签呼叫状态，js 传参：1：正常，3：异常，4：超时
     *     数据库： -2超时 -1 异常 5审核通过 6审核拒绝 7跳过面签 8重新排队（无坐席id） 10挂起（有坐席id）
	 * @param  [channelid]		通道id
	 * @return [type]           [description]
	 */
	public function initVideoInfo(Request $request){
		$params = $request->all();
		$visaId = isset($params['visa_id']) ? $params['visa_id'] : '';
        $videoStatus = isset($params['visa_status']) ? $params['visa_status'] : 0;
		if (empty($visaId)) {
			return json_encode(['code'=>0,'msg'=>'visa参数错误']);
		}
		$channelId = isset($params['channelid']) ? $params['channelid'] : '';

		if (!$videoStatus) {
			return json_encode(['code'=>0,'msg'=>'video_status参数错误']);
		}
		if ($videoStatus != 1) {
			return json_encode(['code'=>1, 'msg'=>'操作成功,video_status=' . $videoStatus . '，不执行任何操作']);
		}
		$visaResultModel = new FastVisaResult();
        //新增审批结果
        $visaResultData = [
            'channel_id' => $channelId,
            'updated_at'=>date('Y-m-d H:i:s')
        ];
        //获取最新的visa_result
        $latestVisaResult = $visaResultModel->getOne(['*'], ['visa_id'=>$visaId], ['id'=>'desc']);
        if (empty($latestVisaResult)) {
            (new ErrorCodeLog())->runLog(config('errorLogCode.initVideoInfo'), ['request'=>$params, 'channel' => $channelId, 'visaRet'=>'']);
            return json_encode(['code'=>0,'msg'=>'插入fast_result异常']);
        }
        $visaResultId = $visaResultModel->updateVisaResult($visaResultData, ['id'=>$latestVisaResult['id']]);
        if (!$visaResultId) return json_encode(['code'=>-2,'msg'=>'系统错误，请联系维护人员']);
		return json_encode(['code'=>1,'msg'=>'操作成功']);
	}

	/**
	 * 面签过程中，截图上传并且去微众做人脸识别
	 * @param Request $request
	 * @return json [图片上传到微众进行人脸识别的结果]
	 */
	public function actionWzFaceUploadFile(Request $request)
	{
		FastGlobal::$retLog = 10;

		$param = $request->all();

		if (empty($param['filedata']) || empty($param['visa_id'])) {
			return json_encode(['code'=>self::CODE_FAIL, 'msg'=>self::MSG_PARAMS]);
		}

		$res = (new WzFace())->wzFaceRecognize($param['visa_id'], $param['filedata']);

		return json_encode($res);
	}

}