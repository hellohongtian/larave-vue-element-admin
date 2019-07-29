<?php
namespace App\Repositories\Face;

use App\Library\Helper;
use App\Library\HttpRequest;
use App\Library\ImageHelper;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaWzFaceResult;
use App\Models\XinFinance\CarLoanOrder;
use App\XinApi\FinanceApi;
use GuzzleHttp\Client;

class WzFace
{

    protected $client;

    //微众人脸识别结果对照图
    public static $wzRecognizeStatusMap = [
        1 => '通过',
        2 => '拒绝',
        3 => '审核中',
        4 => '未识别',
        5 => '无法识别(无公安照片)',
    ];

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * 微众人脸识别（里面其实调用的是旷视人脸识别的接口）
     * @param $visaId
     * @param $base64FileBase
     * @return mixed
     */
    public function wzFaceRecognize($visaId, $base64FileBase)
    {
        try {
            if(!$visaId || !$base64FileBase) {
                throw new \Exception('参数错误');
            }

            $visaInfo = (new FastVisa())->getOne(['id','apply_id','inputted_id','channel_type'], ['id' => $visaId]);
            if (!$visaInfo) {
                throw new \Exception('visa不存在, id:' . $visaId);
            }

            //上传图片至图片服务器
            $uploadResult = Helper::uploadPic($base64FileBase);
            if (!$uploadResult['result']) {
                throw new \Exception( $uploadResult['msg']);
            }
            $picUrl = ImageHelper::uri2URL($uploadResult['pic']);

            //微众人脸识别（已经切换成旷视人脸识别）
            //$wzRet = FinanceApi::wzFaceUploadFile($visaInfo['apply_id'], $visaInfo['inputted_id'], $picUrl, $visaInfo['channel_type']);
            //旷视人脸识别
            //获取姓名和身份证
            $info = (new CarLoanOrder())->getOne(['fullname','id_card_num'],['applyid'=>$visaInfo['apply_id']]);
//            $wzRet = FinanceApi::ksFaceUploadFile($visaInfo['apply_id'], $visaInfo['inputted_id'], $picUrl, $visaInfo['channel_type']);
            $wzRet = FinanceApi::ksFaceUploadFileNew($visaInfo['apply_id'], $visaInfo['inputted_id'], $picUrl,$info['fullname'],$info['id_card_num']);
//            $wzRet = FinanceApi::ksFaceUploadFileNew($visaInfo['apply_id'], $visaInfo['inputted_id'], $picUrl,'索阳','232301199103171311');
            $code = isset($wzRet['code']) ? $wzRet['code'] : 0;
            if ($code != 1) {
                throw new \Exception($wzRet['message']);
            }elseif($code == 1){
                $confidence = $wzRet['data']['result_faceid']['confidence'];
                $le4 = $wzRet['data']['result_faceid']['thresholds']['1e-4'];
                if(bccomp($confidence,$le4,3)){
                    $status = FastVisaWzFaceResult::WZ_FACE_PASS;
                }else{
                    $status = FastVisaWzFaceResult::WZ_FACE_REJECT;
                }
            }else{
                $status = "";
            }

            //将人脸识别结果存到数据库
            $insertData = [
                'visa_id' => $visaInfo['id'],
                'apply_id' => $visaInfo['apply_id'],
//                'status' => $wzRet['data']['status'],
                'status' => $status,
                'pic_url' => $picUrl,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            (new FastVisaWzFaceResult())->insertVisaWzFaceResult($insertData);

            $statusMap = self::$wzRecognizeStatusMap;
            //返回结果
            $ret['code'] = 1;
            $ret['msg'] = 'OK';
            $ret['data'] = isset($statusMap[$status]) ? $statusMap[$status] : '无法识别';

        } catch (\Exception $e) {
            $ret['code'] = -1;
            $ret['msg'] = $e->getMessage();
        }

        return $ret;
    }


    /**
     * 获取微众人脸结果
     * @param $applyid 订单号
     * @param $type 1新车 2二手车
     * @return array
     */
    public function getFaceRet($applyid, $type){
        $ret = [
            'code' => -1,
            'msg' => '',
            'data' => '',
        ];
        if(!$applyid || !$type){
            $ret['msg'] = '参数错误';
            return $ret;
        }

        $type = $type == 1 ? 2 : 1;
        //获取微众人脸结果
        $url = config('common.wz_face_host').config('common.getWzFaceResultUrl');
        $url .= '?applyid=' . $applyid . '&type=' . $type;
        try{
            $response = $this->client->request('get', $url, ['verify' => false]);
            $return = $response->getBody()->getContents();
            $arr_return = json_decode($return, true);
            $code = isset($arr_return['code'])?$arr_return['code']:0;
            if($code != 1){
                $ret['msg'] = $arr_return['message'];
                return $ret;
            }
        } catch (\Exception $e){
            $ret['msg'] = $e->getMessage();
            return $ret;
        }

        $ret['code'] = 1;
        $ret['msg'] = 'OK';
        $ret['data'] = $arr_return['data']['status'];
        return $ret;
    }


}
