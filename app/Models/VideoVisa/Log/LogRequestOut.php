<?php

namespace App\Models\VideoVisa\Log;

use App\Models\VideoVisa\VideoVisaModel;

class LogRequestOut extends VideoVisaModel{
    protected $table='log_request_out';
    public $timestamps=false;
}