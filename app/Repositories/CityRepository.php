<?php
namespace App\Repositories;

use App\Models\Xin\City;
use App\Library\RedisCommon;
use App\Library\Common;

class CityRepository
{

    public function getAllCity($fields=[]){
        $redis = new RedisCommon();
        $city_key = config('common.city_list_redis_key');
        $city_list = $redis->get($city_key);
        if($city_list){
           return $city_list;
        }
        $city_model = new City();
        $res = $city_model->getAll($fields);
        $common = new Common();
        $res = $common->formatArr($res, 'cityid');
        $redis->setex($city_key, $res, 3600 * 24 * 1);//城市信息一般不会修改，改为一个月
        return $res;
    }

    public function getCityInfoByid($cityid){
        if(!$cityid){
            return '';
        }
        $city_list = $this->getAllCity(['cityid','cityname']);
        if(!$city_list){
            return '';
        }
        $city_info = isset($city_list[$cityid])?$city_list[$cityid]:[];
        return isset($city_info['cityname'])?$city_info['cityname']:'';
    }

}
