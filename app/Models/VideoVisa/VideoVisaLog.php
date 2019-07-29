<?php 
namespace App\Models\VideoVisa;

/**
* 日志表
*/
class VideoVisaLog extends VideoVisaModel
{

	protected $table='video_visa_log';
	public $timestamps=false;

    public function writeLog($type, $message){
        $message = is_array($message) ? json_encode($message) : (is_string($message) ? $message : var_export($message, true));
        $data = [
            'type' => $type,
            'text' => $message,
            'create_time' => time(),
        ];
        return $this->insert($data);
    }
	
}
?>