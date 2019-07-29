<?php
namespace App\Models\VideoVisa;

/**
 * 面签结果表
 */
class FastVisaWzFaceResult extends VideoVisaModel
{
    protected $table = 'fast_visa_wz_face_result';
    public $timestamps = false;

    //1通过／2 拒绝／3 审核中／4未识别
    const WZ_FACE_PASS = 1;
    const WZ_FACE_REJECT = 2;
    const WZ_FACE_NONE = 4;

    /**
     * 新增 FastVisaWzFaceResult
     * @param $insertData
     * @return mixed
     */
    public function insertVisaWzFaceResult($insertData)
    {
        return $this->insertGetId($insertData);
    }

    /**
     * 根据visaid 获取最新的人脸识别结果
     * @param $visaId
     * @return string
     */
    public function getWzFaceResult($visaId)
    {
        $result = '';

        $res = $this->getOne(['status'], ['visa_id'=>$visaId], ['id'=>'desc']);
        if ($res) {
            return $res['status'];
        }

        return $result;
    }
}