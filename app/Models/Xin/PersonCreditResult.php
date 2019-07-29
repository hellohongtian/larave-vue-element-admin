<?php

namespace App\Models\Xin;


class PersonCreditResult extends XinModel
{
    const LICENSE_SIGN_DEFAULT = 0;
    const LICENSE_SIGN_DENIED = -1;
    const LICENSE_SIGN_APPROVED = 1;

    protected $table='person_credit_result';
    public $timestamps=false;
}