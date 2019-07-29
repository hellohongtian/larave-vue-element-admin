<?php
/**
 * Created by PhpStorm.
 * User: tanrenzong
 * Date: 18/2/6
 * Time: 下午10:36
 */
namespace App\Http\Controllers\FastVisa;

use App\Http\Controllers\BaseController;
use App\Models\VideoVisa\FastVisaLog;
use Illuminate\Http\Request;

class FastVisaLogController extends BaseController{
    public function updateVideoTime(Request $request)
    {
        $params = $request->all();
        $visaId = isset($params['visa_id']) ? $params['visa_id'] : 0;
        $field = isset($params['field']) ? $params['field'] : '';

        //visa_id
        if (!$visaId) {
            return $this->showMsg(self::CODE_FAIL, self::MSG_PARAMS);
        }

        //需要更新的字段
        if (!in_array($field, ['call_video_time', 'end_video_time'])) {
            return $this->showMsg(self::CODE_FAIL, self::MSG_PARAMS);
        }

        $fastVisaLogModel = new FastVisaLog();
        $latestLog = $fastVisaLogModel->getOne(['id', 'call_video_time'], ['visa_id'=>$visaId], ['id'=>'desc']);
        if (!$latestLog) {
            return $this->showMsg(self::CODE_FAIL, 'fast log 不存在');
        }

        //如果是记录视频结束时间的，但是发现视频发起时间为空，则不执行更新
        if($field == 'end_video_time' && empty($latestLog['call_video_time'])) {
            return $this->showMsg(self::CODE_SUCCESS, '');
        }

        //执行更新
        $fastVisaLogModel->updateVisaLog([$field=>time()], ['id'=>$latestLog['id']]);

        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
    }
}