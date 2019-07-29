<?php
namespace App\Models\VideoVisa\NetEase;
use App\Models\VideoVisa\VideoVisaModel;

/**
 * 网易视频回调表
 */
class FastVideoCallBack extends VideoVisaModel
{

    protected $table = 'fast_video_call_back';
    public $timestamps = false;

    public function getSourcesByChannelId($channelId) {
        
        $fields = array('sources');
        $where = array(
            "channel_id" => $channelId
        );
        return $this->getOne($fields, $where);
    }
}