<?php
namespace App\Models\XinCredit;
use App\Models\BaseModel;

class XinCreditModel extends BaseModel
{
    public $timestamps = false;
    protected $connection = 'mysql.xin_credit';
}