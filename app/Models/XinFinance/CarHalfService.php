<?php

namespace App\Models\XinFinance;


/**
 * 新车超融订单表
 * Class CarBeyondOrder
 * @package App\Models\XinFinance
 */
class CarHalfService extends XinFinanceModel
{
    protected $table = 'car_half_service';
    public $timestamps = false;

    const PURCHASE_TYP_COMMON = 1;//普通
    const PURCHASE_TYP_DIRECTLY = 2;//全国直购


    const PURCHASE_SOURCE_X1 = 1;//x1
    const PURCHASE_SOURCE_X2 = 2;//x2
    const PURCHASE_SOURCE_X3 = 3;//x3
    const PURCHASE_SOURCE_COMMON = 20;//本地购
    const PURCHASE_SOURCE_IM = 21;//IM

    /**
     * 坐席用户组
     * @param string $param
     * @return array|mixed|string
     */
    public static function group_map($param = '')
    {
        $map =  [
            self::PURCHASE_SOURCE_IM => '商城直营',
            self::PURCHASE_SOURCE_X3 => 'X3-合伙人',
            self::PURCHASE_SOURCE_COMMON => '二手车市场',
        ];
        if(!empty($param)) {
            return empty($map[$param])? '':$map[$param];
        }
        return $map;
    }

    public static function purchase_map($param = '')
    {
        $map =  [
            self::PURCHASE_SOURCE_X1 => 'X1',
            self::PURCHASE_SOURCE_X2 => 'X2',
            self::PURCHASE_SOURCE_X3 => 'X3',
            self::PURCHASE_SOURCE_COMMON => '本地购',
            self::PURCHASE_SOURCE_IM => 'IM',
        ];
        if(!empty($param)) {
            return $map[$param];
        }
        return $map;
    }

    /**
     * @note 获取渠道类型 本地购、IM、X1、X3、X2
     * @param int $carid
     * @param int $userid
     * @return int
     */
    public  function get_channel_type($carid,$userid)
    {
//        $car_half_service = $this->car_half_service->getOne(['purchase_type'],['carid' => $carid,'userid'=>$userid],['id'=>'desc']);
        $car_half_service = $this->select(['purchase_type','purchase_source'])
            ->where('carid', $carid)
            ->where('userid', $userid)
            ->orderBy('id', 'DASC')
            ->first();
        $result = self::PURCHASE_SOURCE_COMMON;
        if(!empty($car_half_service)){
            $purchase_type = $car_half_service['purchase_type'];
            $purchase_source = $car_half_service['purchase_source'];
            switch ($purchase_type){
                #本地购
                case $purchase_type == 1:
                    $result = self::PURCHASE_SOURCE_COMMON;
                    break;
                #全国直购
                case $purchase_type == 2:
                    switch ($purchase_source){
                        #线上商城
                        case $purchase_source == 1:
                            $result = self::PURCHASE_SOURCE_IM;
                            break;
                        #x1
                        case ($purchase_source == 4 || $purchase_source == 3):
                            $result = self::PURCHASE_SOURCE_X1;
                            break;
                        #x3
                        case $purchase_source == 100:
                            $result = self::PURCHASE_SOURCE_X3;
                            break;
                        default:
                        $result = self::PURCHASE_SOURCE_COMMON;
                    }
                    break;
            }
        }
        return $result;
    }

}