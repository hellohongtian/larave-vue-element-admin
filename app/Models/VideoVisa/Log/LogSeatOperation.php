<?php

namespace App\Models\VideoVisa\Log;

use App\Models\VideoVisa\VideoVisaModel;

class LogSeatOperation extends VideoVisaModel{
    protected $table='log_seat_operation';
    public $timestamps=false;
}