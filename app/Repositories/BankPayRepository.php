<?php
namespace App\Repositories;

use App\Models\Xin\BankPay;
use App\Library\Common;
use App\Repositories\CityRepository;
use App\Models\Xin\CarHalfApply;
use App\Models\Xin\CarHalfDetail;
use App\Models\Xin\CarNewFeeUser;
use App\Models\Xin\CarHalfFqDetail;
use App\Models\Xin\CarNewFeeFqUser;
use App\Models\Xin\Dealer;
use App\Models\Xin\DealerNew;
use App\Models\Xin\Bank;
use App\Models\Xin\BankBin;


class BankPayRepository
{


    //获取银行卡刷卡列表
    public function getPayHistory($bankno,$cartype = 0){
        if(!$bankno){
            return [];
        }

        $city = new CityRepository();

        //获取消费列表
        $bankpay_list = $this->getBankPayList($bankno,$cartype);
        if(!$bankpay_list){
            return [];
        }
        //城市列表
        $city_list = $city->getAllCity(['cityid','cityname']);


        $carids = array_column($bankpay_list, 'carid');
        $carids = array_unique($carids);
        $cityids = array_column($bankpay_list, 'cityid');
        $cityids = array_unique($cityids);

        //车辆信息
        $car_ret = $this->getCarList($carids);
        $dealerids = isset($car_ret['dealerids'])?$car_ret['dealerids']:[];
        $car_list = isset($car_ret['ret'])?$car_ret['ret']:[];


        //批量获取经销商信息
        $dealer_list = [];
        if($dealerids){
            $dealer_list = $this->getCarDealerList($dealerids,$bankno);
        }

        //借记卡名称和类型
        $firstThree = substr($bankno,0,3);
        $firstFour = substr($bankno,0,4);
        $firstFive = substr($bankno,0,5);
        $firstSix = substr($bankno,0,6);
        $bankno_pre = [$firstThree, $firstFour, $firstFive, $firstSix];
        $bank_bin_model = new BankBin();
        $bank_bin_feilds = ['bank_name', 'card_name'];
        $bank_bin_where = [
            'in' => ['bank_no_pre' => $bankno_pre]
        ];
        $ban_bin = $bank_bin_model->getOne($bank_bin_feilds,$bank_bin_where);

        return [
            'car_list' => $car_list,
            'bankpay_list' => $bankpay_list,
            'dealer_list' => $dealer_list,
            'bank_bin' => $ban_bin,
            'city_list' => $city_list,
        ];
    }

    //经销商信息
    private function getCarDealerList($dealerids,$bankno){
        if(!$dealerids){
            return [];
        }
        $ret = [];
        $ret_0 = [];
        $ret_1 = [];

        $dealerids_0 = isset($dealerids[0])?$dealerids[0]:[];
        $dealerids_0 = array_unique($dealerids_0);
        $dealerids_1 = isset($dealerids[1])?$dealerids[1]:[];
        $dealerids_1 = array_unique($dealerids_1);

        if($dealerids_0){//二手车
            $ret_0 = $this->_getDealerList($dealerids_0, $bankno);
        }
        if ($dealerids_1){//新车
            $ret_1 = $this->_getNewcarDealerList($dealerids_1, $bankno);
        }
        $ret = [0=>$ret_0, 1=>$ret_1];
        return $ret;
    }

    //二手车经销商
    private function _getDealerList($dealerids, $bankno){
        $Common = new Common();
        $dealer_model = new Dealer();
        $bank_model = new Bank();
        $dealer_feilds = ['dealerid','dealername','address','tel'];
        $bank_feilds = ['dealerid','bank_name','bank_no','bank_code','title'];
        $dealer_list = $dealer_model->getAll($dealer_feilds, ['in'=>['dealerid'=>$dealerids]]);
        $bank_list = $bank_model->getAll($bank_feilds, ['in'=>['dealerid'=>$dealerids]]);
        $bank_list = $Common->formatArr($bank_list, 'dealerid');
        if(!$dealer_list){
            return [];
        }

        $ret = [];
        foreach ($dealer_list as $dealer_info){
            $dealerid = $dealer_info['dealerid'];
            $ret[$dealerid] = [
                'dealer_name' => $dealer_info['dealername'],
                'address' => $dealer_info['address'],
                'tel' => $dealer_info['tel'],
                'bank_name' => isset($bank_list[$dealerid]['bank_name'])?$bank_list[$dealerid]['bank_name']:'',
                'bank_no' => isset($bank_list[$dealerid]['bank_no'])?$bank_list[$dealerid]['bank_no']:'',
                'bank_code' => isset($bank_list[$dealerid]['bank_code'])?$bank_list[$dealerid]['bank_code']:'',
                'account_name' => isset($bank_list[$dealerid]['title'])?$bank_list[$dealerid]['title']:'',
            ];
        }

        return $ret;
    }

    //新车经销商
    private function _getNewcarDealerList($dealerids, $bankno){
        $dealer_new_model = new DealerNew();
        $feilds = ['id','dealer_name','address','mobile','bank_branch_name','bank_no','account_name','bank_number','account_name'];
        $dealer_list = $dealer_new_model->getAll($feilds, ['in'=>['id'=>$dealerids]]);
        $ret = [];
        if($dealer_list){
            foreach ($dealer_list as $dealer_info){
                $dealerid = $dealer_info['id'];
                $ret[$dealerid] = [
                    'dealer_name' => $dealer_info['dealer_name'],
                    'address' => $dealer_info['address'],
                    'tel' => $dealer_info['mobile'],
                    'bank_name' => $dealer_info['bank_branch_name'],
                    'bank_no' => $dealer_info['bank_no'],
                    'title' => $dealer_info['account_name'],
                    'bank_code' => $dealer_info['bank_number'],
                    'account_name' => $dealer_info['account_name'],
                ];
            }
        }
        return $ret;
    }


    //车辆信息
    private function getCarList($carids){
        //car_half_apply
        $apply_model = new CarHalfApply();
        $apply_fields = ['carid','dealerid','car_type','fullname','id_card_num','clr_type','finance_status','ver','userid','channel_type','product_stcode'];
        $apply_where = [
            'userid >' => 0,
            'in' => ['carid' => $carids]
        ];
        $apply_list = $apply_model->getAll($apply_fields, $apply_where);
        if(!$apply_list){
            return [];
        }
        $common = new Common();
        //半价车详情
        $carDetailInfo = $this->getCarHalfDetailByCarid($apply_list);
        //产品方案
        $BusinessType = $common->getAllProductScheme();

        //dealerid
        $dealerids = [];

        //车辆价格
        $ret = [];
        foreach ($apply_list as $apply_key => $apply_info){

            $dealerids[$apply_info['car_type']][] = $apply_info['dealerid'];
            $carid = $apply_info['carid'];
            if($apply_info['product_stcode'])
            $pro_code_key = $apply_info['channel_type'].'_'.$apply_info['product_stcode'];

            $ret[$carid] = [
                'dealerid' => $apply_info['dealerid'],
                'carid' => $apply_info['carid'],
                'business_code' => $apply_info['car_type'],
                'fullname' => $apply_info['fullname'],
                'id_card_num' => $apply_info['id_card_num'],
                'business_type' => isset($BusinessType[$pro_code_key])?$BusinessType[$pro_code_key]:'',
                'clr_type' => $apply_info['clr_type'],
                'finance_status' => $apply_info['finance_status'],
                'car_type' => $apply_info['car_type'],
            ];

            if($apply_info['car_type'] == 0 && isset($carDetailInfo[$carid]['price_settlement'])){
                $ret[$carid]['price'] = number_format($carDetailInfo[$carid]['price_settlement'], 2);
            }elseif($apply_info['car_type'] == 1 && isset($carDetailInfo[$carid]['price_final'])){
                $ret[$carid]['price'] = number_format($carDetailInfo[$carid]['price_final'] / 100, 2);
            }

        }
        return ['dealerids'=>$dealerids,'ret'=>$ret];
    }

    //根据carid获取半价车详细信息,会根据ver做判断
    private function getCarHalfDetailByCarid($apply_list){
        if(!$apply_list){
            return [];
        }

        //var=2 car_type=0
        $car_var2_type0 = [];
        //var=2 car_type=1
        $car_var2_type1 = [];
        //var=3 car_type=0
        $car_var3_type0 = [];
        //var=3 car_type=1
        $car_var3_type1 = [];

        foreach ($apply_list as $apply_info){
            if($apply_info['ver'] == 2 && $apply_info['car_type'] == 0){
                $car_var2_type0[] = $apply_info['carid'];
            }
            if($apply_info['ver'] == 2 && $apply_info['car_type'] == 1){
                $car_var2_type1[] = ['carid'=>$apply_info['carid'], 'userid'=>$apply_info['userid']];
            }
            if($apply_info['ver'] == 3 && $apply_info['car_type'] == 0){
                $car_var3_type0[] = $apply_info['carid'];
            }
            if($apply_info['ver'] == 3 && $apply_info['car_type'] == 1){
                $car_var3_type1[] = ['carid'=>$apply_info['carid'], 'userid'=>$apply_info['userid']];
            }
        }

        //获取数据
        $var2_type0_data = [];
        $var2_type1_data = [];
        $var3_type0_data = [];
        $var4_type1_data = [];

        if($car_var2_type0){
            $apply_detail_model = new CarHalfDetail();
            $var2_type0_data = $apply_detail_model->getAll(['carid','price_settlement'],['in'=>['carid'=>$car_var2_type0]]);
        }

        if($car_var2_type1){
            $car_new_fee_model = new CarNewFeeUser();
            foreach ($car_var2_type1 as $var2_info){
                $where_new_fee = ['carid'=>$var2_info['carid'],'userid'=>$var2_info['userid']];
                $var2_data = $car_new_fee_model->getOne(['carid','price_settlement','price_final'],$where_new_fee);
                if($var2_data){
                    $var2_type1_data[] = $var2_data;
                }
            }
        }

        if($car_var3_type0){
            $fq_detail_model = new CarHalfFqDetail();
            $var3_type0_data = $fq_detail_model->getAll(['carid','price_settlement'],['in'=>['carid'=>$car_var3_type0]]);
        }

        if($car_var3_type1){
            $new_fq_detail_model = new CarNewFeeFqUser();
            foreach ($car_var3_type1 as $var3_info){
                $where_new_fq_detail = ['carid'=>$var3_info['carid'],'userid'=>$var3_info['userid']];
                $var4_data = $new_fq_detail_model->getOne(['carid','price_settlement','price_final'],$where_new_fq_detail);
                if($var4_data){
                    $var4_type1_data[] = $var4_data;
                }
            }
        }
        $var_data = array_merge($var2_type0_data, $var2_type1_data, $var3_type0_data, $var4_type1_data);
        $Common = new Common();
        $var_data = $Common->formatArr($var_data, 'carid');
        return $var_data;
    }

    //消费列表
    private function getBankPayList($bankno, $cartype){
        if($cartype == 0){
            $cartype = '01';
        }else{
            $cartype = '10';
        }
        $bank_pay = new BankPay();
        return $bank_pay->getAll(['carid','cityid','bank_no','createtime','amount'],['bank_no'=>$bankno,'business_code'=>$cartype],['pay_time'=>'desc']);
    }



}
