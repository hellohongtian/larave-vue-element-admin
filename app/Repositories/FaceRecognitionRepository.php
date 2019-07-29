<?php
namespace App\Repositories;

use App\Models\SysFinance\FaceRecognition;

class FaceRecognitionRepository
{

    public $face = null;

    public function __construct()
    {
        $this->face = new FaceRecognition();
    }

    //获取单条信息
    public function getInfoByCondition($fileds=['*'], $params){
        $where = [];
        if(isset($params['applyid'])){
            $where['applyid'] = $params['applyid'];
        }
        if(!$where){
            return [];
        }
        return $this->face->getOne($fileds, $where);
    }


}
