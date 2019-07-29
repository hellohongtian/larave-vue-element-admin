<?php
/**
 * Created by PhpStorm.
 * User: tanrenzong
 * Date: 18/1/31
 * Time: 下午6:30
 */

namespace App\Models\VideoVisa;


use App\Models\BaseModel;

class Role extends VideoVisaModel
{
    protected $table = 'role';
    public $timestamps = false;

    const FLAG_FOR_RISK = 3;
}