<?php
namespace App\Models\VideoVisa\NetEase;
use App\Models\VideoVisa\VideoVisaModel;

/**
 * 网易视频回调表
 */
class FastVideoData extends VideoVisaModel
{

    protected $table='fast_video_data';
    public $timestamps=false;

    /**
     * 根据visa_id获取视频url
     * @param $visaId
     * @return string
     */
    public function getVideoUrlByVisaId($visaId)
    {
        $result = '';

        $video = $this->getOne(['url'], ['visa_id' => $visaId], ['id' => 'desc']);
        if ($video) {
            $result = $video['url'];
        }

        return $result;
    }
    /**
     * 根据visa_id获取视频url
     * @param $visaId
     * @return string
     */
    public function getVideoUrlByVisaIdAll($visaId)
    {
        $result = [];

        $video = $this->getAll(['url'], ['visa_id' => $visaId], ['order_id' => 'asc']);
        if ($video) {
            $result = array_unique(array_filter(array_column($video,'url')));
        }

        return $result;
    }


}
?>