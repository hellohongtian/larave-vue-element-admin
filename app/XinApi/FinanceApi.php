<?php
/**
 * finance.youxinjinrong.com的相关接口
 */
namespace App\XinApi;

use App\Library\Helper;
use App\Library\HttpRequest;
use App\Models\Xin\ErpMaster;
use App\Repositories\UserRepository;

class FinanceApi {

    /**
     * 微众 人脸识别
     * wiki：http://doc.xin.com/pages/viewpage.action?pageId=6983459
     */
    const WZ_FACE_UPLOAD_FILE_URL_TEST = 'http://wzface_finance.finance.ceshi.youxinjinrong.com/api/wzface/face-uploadFile';
    const WZ_FACE_UPLOAD_FILE_URL = 'http://finance.youxinjinrong.com/api/wzface/face-uploadFile';
    public static function wzFaceUploadFile($applyId, $inputtedId, $fileUrl, $type)
    {
        $paramList = [
            'applyid' => $applyId,
            'inputted_id' => $inputtedId,
            'file_url' => $fileUrl,
            'type' => $type,
        ];
        $url = (Helper::isProduction()) ? self::WZ_FACE_UPLOAD_FILE_URL : self::WZ_FACE_UPLOAD_FILE_URL_TEST;
        return HttpRequest::doParamPostJson($url, ['verify' => false,'form_params' => $paramList]);
    }

    /**
     * 旷视人脸识别
     * wiki: http://doc.xin.com/pages/viewpage.action?pageId=7006523
     */
    const KS_FACE_UPLOAD_FILE_URL_TEST = 'http://wzface_finance.finance.ceshi.youxinjinrong.com/api/sface/face-uploadFile';
    const KS_FACE_UPLOAD_FILE_URL = 'http://finance.youxinjinrong.com/api/sface/face-uploadFile';
    public static function ksFaceUploadFile($applyId, $inputtedId, $fileUrl, $type)
    {
        $paramList = [
            'applyid' => $applyId,
            'inputted_id' => $inputtedId,
            'file_url' => $fileUrl,
            'type' => $type,
        ];
        $url = (Helper::isProduction()) ? self::KS_FACE_UPLOAD_FILE_URL : self::KS_FACE_UPLOAD_FILE_URL_TEST;

        return HttpRequest::doParamPostJson($url, ['verify' => false,'form_params' => $paramList]);
    }

    /**
     * 旷视人脸识别上传新
     * wiki: http://doc.xin.com/pages/viewpage.action?pageId=7006523
     */
    const KS_FACE_UPLOAD_FILE_URL_NEW_TEST = 'http://face.ceshi.youxinjinrong.com/face_ks/ks_uploadfile';
    const KS_FACE_UPLOAD_FILE_URL_NEW = 'https://face.youxinjinrong.com/face_ks/ks_uploadfile';
    public static function ksFaceUploadFileNew($applyId, $inputtedId, $fileUrl,$id_card_name,$id_card_number)
    {
        if (Helper::isProduction()) {
            $app_id = 10004;
            $secret = 'HeLuBK1ncgCwBfvEQP6nk1XxZ8lUFUXN';
        }else{
            $app_id = 10012;
            $secret = 'zbzIW4r4QfRewKWbnQzXUfy3pk84ryYf';
        }
        $paramList = [
            'app_id' => $app_id,
            'applyid' => $applyId,
            'inputted_id' => $inputtedId,
            'file_url' => $fileUrl,
            'type' => 7,
            'id_card_name' => $id_card_name,
            'id_card_number' => $id_card_number
        ];
        $sign = function ($data) use ($secret) {
            ksort($data);
            $str = '';
            foreach ($data as $key => $value) {
                $str .="&$key=$value";
            }
            $str = trim($str, "&") . $secret;
            return strtolower(md5($str));
        };
        $paramList['sign'] = $sign($paramList);
        $url = (Helper::isProduction()) ? self::KS_FACE_UPLOAD_FILE_URL_NEW : self::KS_FACE_UPLOAD_FILE_URL_NEW_TEST;
        return HttpRequest::doParamPostJson($url, ['verify' => false,'form_params' => $paramList]);
    }
}