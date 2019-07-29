<?php
/**
 * video_visa库Model基类
 * Class VideoVisaModel
 * @package App\Models\VideoVisa
 */

namespace App\Models\VideoVisa;

use App\Models\BaseModel;
use App\Models\VideoVisa\SeatManage;

class VideoVisaModel extends BaseModel {
	protected $connection = 'mysql.video_visa';
	protected $table = 'visa_remark';

	public $timestamps = false;

}
