<?php
namespace App\Console\Commands;

use App\Fast\FastKey;
use App\Library\HttpRequest;
use App\Models\VideoVisa\ErrorCodeLog;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\NetEase\FastVideoCallBack;
use App\Models\VideoVisa\NetEase\FastVideoData;

class VideoCommand extends BaseCommand
{
    protected $signature = 'VideoCommand {func} ';
    protected $description = '视频相关的脚本';

    public function handle()
    {
        $func = $this->argument('func');
        $this->{$func}();
    }

    /**
     * 根据当前更新时间跑脚本将fast_video_call_back数据写入fast_video_data表
     */
    private function callbackToVideo()
    {
        $curTime = time();
        $curDayStartTime = strtotime(date('Y-m-d'));

        $lastTime = $this->redisObj->get(FastKey::CALLBACK_TO_VIDEO_TIME_KEY);
        if ($lastTime === false) $lastTime = $curDayStartTime - 30;
        $this->_callbackToVideo($lastTime);
        $this->redisObj->setex(FastKey::CALLBACK_TO_VIDEO_TIME_KEY, $curTime, 24 * 3600);
    }

    /**
     * 根据指定时间段跑脚本将fast_video_call_back数据写入fast_video_data表
     * @return bool
     */
    private function callbackToVideoByTimeSpan()
    {
        $timeSpan = $this->redisObj->get(FastKey::CALLBACK_TO_VIDEO_START_END_TIME_KEY, 1);
        if ($timeSpan === false) {
            return false;
        }
        $timeSpan = explode('-', $timeSpan);
        $startTime = isset($timeSpan[0]) ? $timeSpan[0] : '';
        $endTime = isset($timeSpan[1]) ? $timeSpan[1] : '';

        if (!$startTime || !$endTime) {
            return false;
        }

        $this->_callbackToVideo($startTime, $endTime);

        $this->redisObj->delete(FastKey::CALLBACK_TO_VIDEO_START_END_TIME_KEY);
    }


    private function _callbackToVideo($startTime = '', $endTIme = '')
    {
        if ($startTime && $endTIme) {
            $where['create_time >='] = $startTime;
            $where['create_time <='] = $endTIme;
        } else if ($startTime && !$endTIme) {
            $where['create_time >='] = $startTime;
        } else {
            return false;
        }
        $callbackObj = new FastVideoCallBack();
        $callbackList = $callbackObj->getAll(['sources'], $where);
        foreach ($callbackList as $tempCallback) {
            $tempInfo = json_decode($tempCallback['sources'],true);
            if (!isset($tempInfo['event_type'])) {
                continue;
            }
            $eventType = $tempInfo['event_type'];
            if ($eventType == 100) {
                $channelId = isset($tempInfo['channel_id']) ? $tempInfo['channel_id'] : '';
                if(!empty($channelId)) {
                    $this->dataToVideo($channelId, $tempInfo);
                }
            }
        }
    }

    /**
     *
     * 1个fileinfo可能有回调多个channelid，参考论坛： http://bbs.netease.im/forum.php?mod=viewthread&tid=625&highlight=channelid
     * 回调的数据格式：
     * {"eventType":"6","fileinfo":"[{\"caller\":true,\"channelid\":\"6290737000999815988\",\"filename\":\"xxxxxx.type\",\"md5\":\"a9b248e29669d588dd0b10259dedea1a\",\"mix\":false,\"size\":\"2167\",\"type\":\"gz\",\"vid\":\"1062591\",\"url\":\"http://xxxxxxxxxxxxxxxxxxxx.type\",\"user\":\"zhangsan\"}]"}
     */
    private function dataToVideo($channelId, $fileInfo) {

        $temp_arr = explode('_',$channelId);
        $channelIdEnv = end($temp_arr);
        if($channelIdEnv == 2 && is_production_env()){//生产环境调测试环境
            $url = C('common.test_video_call_back');
            HttpRequest::getJson($url, ['source' => json_encode($fileInfo)]);
            return;
        }
        $videoObj = new FastVideoData();
        $videoData = $videoObj->getOne(['id','visa_id'], ['channel_id' => $channelId], ['id' => 'desc']);
        if (empty($videoData)){
            return;
        }
        $data = [
            'event_type' => 100,
            'channel_id' => $channelId,
            'file_name' => isset($fileInfo['file_id']) ? $fileInfo['file_id'] : '',
            'mix' => 1,
            'size' => isset($fileInfo['file_size']) ? $fileInfo['file_size'] : '',
            'type' => isset($fileInfo['file_format']) ? $fileInfo['file_format'] : '',
            'vid' => isset($fileInfo['video_id']) ? $fileInfo['video_id'] : '',
            'url' => isset($fileInfo['video_url']) ? $fileInfo['video_url'] : '',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $videoObj->updateBy($data, ['id' => $videoData['id']]);
    }

}