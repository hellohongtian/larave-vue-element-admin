<?php
namespace App\Models\XinFinance;
use App\Models\BaseModel;

class XinFinanceModel extends BaseModel
{
    public $timestamps = false;
    protected $connection = 'mysql.finance';
}