<?php
/**
 * Created by PhpStorm.
 * User: tanrenzong
 * Date: 18/3/19
 * Time: 下午3:57
 */
namespace App\XinApi;

use App\Library\Helper;
use App\Library\HttpRequest;

class ErpApi {
    /**
     * 查询erp用户
     * wiki:http://doc.xin.com/pages/viewpage.action?pageId=6994692
     * @param $condition
     * @return mixed
     */
//    const GET_MASTER_INFO_URL_TEST = 'http://xgrc.erp.ceshi.youxinjinrong.com/search/erpMaster';
    const GET_MASTER_INFO_URL_TEST = 'http://erp.ceshi.youxinjinrong.com/search/erpMaster';
    const GET_MASTER_INFO_URL = 'https://erp.youxinjinrong.com/search/erpMaster';
    public static function getMasterInfo($condition)
    {
        $params = [];
        if (isset($condition['master_names'])) {
            $params['masternames'] = implode(',', $condition['master_names']);
        } else if (isset($condition['master_ids'])) {
            $params['masterids'] = implode(',', $condition['master_ids']);
        }

        $url = (Helper::isProduction()) ? self::GET_MASTER_INFO_URL : self::GET_MASTER_INFO_URL_TEST;
        return HttpRequest::getJson($url, $params);
    }

    /**
     * wiki: http://doc.xin.com/pages/viewpage.action?pageId=6994696
     */
//    const GET_CAR_FEE_URL_TEST = "http://xgrc.erp.ceshi.youxinjinrong.com/search/orderFee";
//    const GET_CAR_FEE_URL_TEST = "http://jira241.erp.ceshi.youxinjinrong.com/search/orderFee";
//    const GET_CAR_FEE_URL = "https://erp.youxinjinrong.com/search/orderFee";
    const GET_CAR_FEE_URL_TEST = "http://cfapi.fat.youxinjinrong.com/search/orderFee";
    const GET_CAR_FEE_URL = "http://cfapi.youxinjinrong.com/search/orderFee";
    public static function getCarFee($applyid) {
        // 付一半订单费用明细表
        /*$feilds = [
            'id','applyid','userid','carid','erp_first_pay','delay_fee','gps_fee',
            'erp_diff_price','erp_interest_mortgage','erp_interest_credit','erp_yougu_fee',
            'erp_sf_interest','gap','erp_profit_total','erp_youzhen_fee','manage_fee',
            'sf_manage','deposit_fee','sf_dealer_service','sf_com_ins_cl',
            'sf_com_ins','sf_gps','erp_total_price','erp_price_half','price_settlement',
            'price_half','final_payment','credit_loan','scrap_value','pos_price','mortgage_loan',
            'total_loan','sf_total_loan','car_fee_amount','sf_interest_lease_fee','erp_interest_total',

        ];
        return (new CarHalfOrderFeeDetail())->getOne($feilds, ['applyid'=>$applyid]);*/

        $result = [];
        $params['applyid'] = $applyid;

        $url = Helper::isProduction() ? self::GET_CAR_FEE_URL : self::GET_CAR_FEE_URL_TEST;

        $response = HttpRequest::getJson($url, $params);

        if ($response['code'] == 1 && isset($response['data'])) {
            $result = $response['data'];
        }

        return $result;
    }

    /**
     * wiki: http://doc.xin.com/pages/viewpage.action?pageId=6994694
     */
    const GET_REMARK_INFO_URL_TEST = "http://erp.ceshi.youxinjinrong.com/search/carHalfRemark";
    const GET_REMARK_INFO_URL = "https://erp.youxinjinrong.com/search/carHalfRemark";
    public static function getRemarkInfo($condition) {
//        return (new CarHalfRemark())->getAll('*',$condition);

        $result = [];
        $params['type'] = $condition['type'];
        $params['ids'] = $condition['ids'];
        $url = Helper::isProduction() ? self::GET_REMARK_INFO_URL : self::GET_REMARK_INFO_URL_TEST;

        $response = HttpRequest::getJson($url, $params);

        if ($response['code'] == 1 && isset($response['data'])) {
            $result = $response['data'];
        }

        return $result;
    }

    /**
     * wiki: http://doc.xin.com/pages/viewpage.action?pageId=8546475
     */
    const GET_OUTSIDE_INFO_URL_TEST = "http://move_third.foundation.ceshi.youxinjinrong.com/api/third/third_data";
    const GET_OUTSIDE_INFO_URL = "http://foundation.youxinjinrong.com/api/third/third_data";
    public static function getOutsideInfo($condition) {

        $result = [];
        $url = Helper::isProduction() ? self::GET_OUTSIDE_INFO_URL : self::GET_OUTSIDE_INFO_URL_TEST;
        $response = HttpRequest::getJson($url, $condition);
//        $response = HttpRequest::getJson("http://xinyan.foundation.ceshi.youxinjinrong.com/api/third/third_data?fullname=%E7%BB%8C%E5%97%9A%E6%87%80%E6%B5%BC%EF%BF%BD&id_card_num=341421196308111496&mobile=14698363519&bank_no=4682037998363519&menu=xy&type=xyCreditArchives&phone_no=14698363519&id_no=341421196308111496");
//        $response = HttpRequest::getJson("http://foundation.youxinjinrong.com/api/third/third_data?fullname=%E5%8A%AA%E5%B0%94%E4%B9%B0%E4%B9%B0%E6%8F%90%C2%B7%E5%85%8B%E7%83%AD%E6%9C%A8&id_no=652923199209191432&mobile=15809073644&bank_no=6222033014001382247&menu=xy&type=xyCreditArchives");
        if (isset($response['retData']['code']) && $response['retData']['code'] == 1) {
            $result = $response['retData']['data'];
        }

        return $result;
    }
}