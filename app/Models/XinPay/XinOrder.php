<?php
/**
 * Created by PhpStorm.
 * User: zhangxiaomin1
 * Date: 2018/10/31
 * Time: 10:56
 */

namespace App\Models\XinPay;

class XinOrder extends XinPayModel
{
    protected $table='xin_order';
    public $timestamps=false;
}