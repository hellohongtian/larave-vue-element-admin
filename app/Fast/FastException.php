<?php

namespace App\Fast;

use Exception;

class FastException extends Exception{
    protected $uid;
    protected $extraData;

    public function __construct($msg, $code = 0, $uid = 0, $extraData=[]){
        parent::__construct($msg, $code);
        $this->uid = empty($uid) ? 0 : intval($uid);
        $this->extraData = (empty($extraData)) ? null : $extraData;
    }

    public static function throwException($msg, $code = 0, $uid = 0, $extraData=[]){
        throw new FastException($msg, $code, $uid, $extraData);
    }

    public function getExceptionRet(){
        $ret = array(
            'code' => $this->getCode(),
            'msg'  => $this->getMessage(),
            'data' => null,
        );
        return $ret;
    }

    public function getUid(){
        return $this->uid;
    }

    public function getExtra(){
        return $this->extraData;
    }
}

