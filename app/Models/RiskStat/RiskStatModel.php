<?php
namespace App\Models\RiskStat;

use App\Models\BaseModel;

class RiskStatModel extends BaseModel
{
    public $timestamps = false;
    protected $connection = 'mysql.risk_stat';
}