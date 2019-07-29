<?php
namespace App\Repositories;


use App\Fast\FastException;
use App\Fast\FastKey;
use App\Library\RedisCommon;
use App\Models\NewCar\CxMake;
use App\Models\NewCar\CxModeFinance;
use App\Models\NewCar\DealerBank;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\VideoVisaModel;
use App\Models\VideoVisa\VisaPool;
use App\Models\VideoVisa\VisaRemark;
use App\Models\VideoVisa\VisaRemarkAttach;
use App\Models\Xin\Bank;
use App\Models\Xin\Car;
use App\Models\Xin\CarDetail;
use App\Models\Xin\CarHalfApply;
use App\Models\Xin\CarHalfDetail;
use App\Models\Xin\CkCarTask;
use App\Models\Xin\CollectCar;
use App\Models\Xin\CxBrand;
use App\Models\Xin\CxMode;
use App\Models\Xin\CxSeries;
use App\Models\Xin\Dealer;
use App\Models\XinCredit\PersonCredit;
use App\Models\XinCredit\WebankApplyData;
use App\Models\XinFinance\CarLoanOrder;
use App\Models\XinFinance\CarLoanOrderMore;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class FaceAuthRepository
{
    const CHANNEL_TYPE_OLD = 1;
    const CHANNEL_TYPE_NEW = 2;
    /**
     * 根据条件获取面签待审列表-分页
     * @param array $fields
     * @param array $params
     * @return mixed
     */
    public function getList($fields = ['*'], $params = []){
        $where = [];
        $where['in'] = ['status' => [2,3,4]];
        if(isset($params['status'])){
            if(is_array($params['status'])){
                $status_in = ['status' => $params['status']];
                $where['in'] = array_merge($where['in'], $status_in);
            } else {
                $where['status'] = $params['status'];
            }
        }

        if(isset($params['start_time']) && isset($params['end_time'])){
            $where['create_time >'] = $params['start_time'];
            $where['create_time <'] = $params['end_time'];
        }

        if(isset($params['applyid'])){
            $where['applyid'] = $params['applyid'];
        }

        if(isset($params['mobile'])){
            $where['mobile'] = $params['mobile'];
        }

        if(isset($params['carid'])){
            $where['carid'] = $params['carid'];
        }

        if(isset($params['channel'])){
            $where['channel'] = $params['channel'];
        }

        if(isset($params['business_type'])){
            $where['business_type'] = $params['business_type'];
        }

        if(isset($params['fullname'])){
            $where['fullname like'] = $params['fullname'];
        }

        if(isset($params['applyid'])){
            $where['applyid'] = $params['applyid'];
        }

        if(isset($params['seat_id'])){
            $where['seat_id'] = $params['seat_id'];
        }

        if(isset($params['risk_at'])){
            $where['risk_at'] = $params['risk_at'];
        }
        if(isset($params['car_cityid'])){
            $where['car_cityid'] = $params['car_cityid'];
        }
        if(isset($params['risk_start_name'])){
            $where['risk_start_name'] = $params['risk_start_name'];
        }
        if(!$where['in']){
            unset($where['in']);
        }

        $visa_pool_model = new VisaPool();
        $ret = $visa_pool_model->getList($fields, $where);
        return $ret;
    }

    /**
     * 根据条件获取面签挂起列表-分页
     * @param array $fields
     * @param array $params
     * @return mixed
     */
    public function getHangList($fields = ['*'], $params = []){
        $masterid = $session = Session::get('uinfo')['seat_id'];
        $where = ['seat_id' => $masterid,'is_hang' => 1];
        $visa_pool_model = new VisaPool();

        $ret = $visa_pool_model->getList($fields, $where);

        return $ret;
    }

    /**
     * 根据条件获取面签待审详情
     * @param array $fields
     * @return mixed
     */
    public function getDetailVisa($applyid){
        if(!$applyid){
            return [];
        }
        $visa_pool_model = new VisaPool();
        $info = $visa_pool_model->getOne(['*'],['applyid'=>$applyid]);
        return $info;
    }

    /**
     * 根据条件获取订单详情
     * @param array $fields
     * @return mixed
     */
    public function getCarHalfApply($applyid){
        if(!$applyid){
            return [];
        }
        $car_half_apply = new CarHalfApply();
        $info = $car_half_apply->getOne(['*'],['applyid'=>$applyid]);
        return $info;
    }

    /**
     * 根据条件获取客户详情
     * @param array $fields
     * @return array
     */
    public function getUserInfo($webank_apply_data_id){
        if(!$webank_apply_data_id){
            return [];
        }
        $person_credit = new PersonCredit();
        $info = $person_credit->getOne(['*'],['webank_apply_data_id'=>$webank_apply_data_id]);
        return $info;
    }

    /**
     * 根据条件获取经销商信息
     * @param array $fields
     * @return array
     */
    public function getDealerInfo($dealerId,$channelType){
        if(!$dealerId && !$channelType){
            return [];
        }
        if($channelType == self::CHANNEL_TYPE_OLD){
            $dealer = (new Dealer())->getOne(['*'], ['dealerid' => $dealerId]);
            $bank   = (new Bank())->getOne(['*'],['dealerid' => $dealerId]);
            if($dealer && $bank){
                $dealerInfo = [
                    'dealer_name' => isset($dealer['dealername']) ? $dealer['dealername'] : '',
                    'address' => isset($dealer['address']) ? $dealer['address'] : '',
                    'bank_name' => isset($bank['bank_name']) ? $bank['bank_name'] : '',
                    'bank_no' => isset($bank['bank_no']) ? $bank['bank_no'] : '',
                    'title' => isset($bank['title']) ? $bank['title'] : '',
                    'bank_code' => isset($bank['bank_code']) ? $bank['bank_code'] : '',
                ];
            }else{
                return [];
            }
        }elseif ($channelType == self::CHANNEL_TYPE_NEW){
            $dealer = new \App\Models\NewCar\Dealer();
            $dealer = $dealer->getOne(['*'], ['dealerid' => $dealerId]);
            $bank   = (new DealerBank())->getOne(['*'], ['dealerid' => $dealerId]);
            if($dealer && $bank){
                $dealerInfo = [
                    'dealername' => isset($dealer['dealername']) ? $dealer['dealername'] : '',
                    'address' => isset($dealer['address']) ? $dealer['address'] : '',
                    'bank_name' => isset($bank['bank_name']) ? $bank['bank_name'] : '',
                    'bank_no' => isset($bank['bank_no']) ? $bank['bank_no'] : '',
                    'title' => isset($bank['account_name']) ? $bank['account_name'] : '',
                    'bank_code' => isset($bank['bank_code']) ? $bank['bank_code'] : '',
                ];
            }
        }
        return $dealerInfo;
    }

    /**
     * 根据条件获取车辆信息
     * @param array $fields
     * @return array
     */
    public function getCarInfo($apply){
        if(!$apply){
            return [];
        }
        if($apply['channel_type'] == 1){
            $carInfo = (new Car())->getOne('*', ['carid' => $apply['carid']]);
            $brand   = (new CxBrand())->getOne('brandname',['brandid'=>$carInfo['brandid']]);
            $series  = (new CxSeries())->getOne('seriesname',['seriesid'=>$carInfo['seriesid']]);
            $carDetail = (new CarDetail())->getOne('*',['carid' => $apply['carid']]);
            $model   = (new CxMode())->getOne('modename',['modeid'=>$carInfo['modeid']]);
            $car_loan_order_info = (new CarLoanOrder())->getOne('vin',['applyid'=>$apply['applyid']]);
            $car_loan_order_more_info = (new CarLoanOrderMore())->getOne('car_regist_date,car_series_name,car_mode_name,car_brand_name', ['applyid'=>$apply['applyid']]);
            //是否有复检
            $recheckResult = $this->getRecheckResultOfByCarid($apply['carid']);
            $carInfo = [
                'carid' => $carInfo['carid'],
                'color' => !empty($carDetail['color_remark']) ? $carDetail['color_remark'] : '',
                'vin'   => $carDetail['vin'],
                'brandname' => $brand['brandname'],
                'seriesname' => $series['seriesname'],
                'modename' => $model['modename'],
                'registdate' => $carInfo['regist_date'], //首次上牌日期
                'enginenum'  => $carDetail['engine_num'], //发动机号
                'vin_pay'    => $car_loan_order_info['vin'], //刷卡时vin
                'series_mode'=> $car_loan_order_more_info['car_series_name'].' '.$car_loan_order_more_info['car_mode_name'], //刷卡时车系车型
                'car_regist_date' => date('Y-m-d', strtotime($car_loan_order_more_info['car_regist_date'])), //刷卡时间
                'recheckResult'  => $recheckResult,
            ];
        }elseif ($apply['channel_type'] == 2){
            $webank_data = (new WebankApplyData())->getOne(['commit_longitude', 'commit_latitude', 'from_mode_id', 'submit_entry', 'city_id', 'username', 'operator_id'], ['id' => $apply['webank_apply_data_id']]);
            $mode_info   = (new \App\Models\NewCar\CxMode())->getOne(['seriesid', 'brandid', 'makeid','modename'], ['modeid' => $webank_data['from_mode_id']]);
            $brand_info  = !empty($mode_info['brandid']) ? (new \App\Models\NewCar\CxBrand())->getOne(['brandname'], ['brandid' => $mode_info['brandid']]) : '';
            $series_info = !empty($mode_info['seriesid']) ? (new \App\Models\NewCar\CxSeries())->getOne(['seriesname'], ['seriesid' => $mode_info['seriesid']]) : '';
            $make_info   = !empty($mode_info['makeid']) ? (new CxMake())->getOne(['makename'], ['makeid' => $mode_info['makeid']]) : '';
            $brandName = !empty($brand_info) ? $brand_info['brandname'] : '';
            $makeName = !empty($make_info) ? $make_info['makename'] : '';
            $seriesName = !empty($series_info) ? $series_info['seriesname'] : '';
            $modeName = !empty($mode_info) ? $mode_info['modename'] : '';

            $credit_info = empty($webank_data) ? '无' : $brandName . ' ' . $makeName . ' ' . $seriesName . ' ' . $modeName;

            $cxModeFinanceData = (new CxModeFinance())->getOne('*',['modeid' => $apply['carid']]);
            $car_loan_order_info         = (new CarLoanOrder())->getOne('*',['applyid'=>$apply['applyid']]);
            $car_loan_order_more_info          = (new CarLoanOrderMore())->getOne('*',['applyid'=>$apply['applyid']]);
            $vinNumber ='';
            if(!empty($car_loan_order_more_info['vin_json'])){
                $vinJson = json_decode($car_loan_order_more_info['vin_json'],true);
                $vinNumber = !empty($vinJson['vin']) ? $vinJson['vin'] : '';
            }
            $vin = !empty($car_loan_order_info['vin']) ? strtoupper($car_loan_order_info['vin']) : strtoupper($vinNumber);
            $carInfo = [
                'carid' => $car_loan_order_info['carid'],
                'brandname' => $car_loan_order_more_info['car_brand_name'],
                'seriesname' => $car_loan_order_more_info['car_series_name'],
                'carname' => $car_loan_order_more_info['car_name'],
                'modename' => $car_loan_order_more_info['car_mode_name'],
                'color' => $car_loan_order_more_info['car_color'],
                'enginenum' => strtoupper($car_loan_order_more_info['engine_num']),
                'xin_guide_price' => !empty($cxModeFinanceData['xin_guide_price']) ? $cxModeFinanceData['xin_guide_price'] * 10000 : 0,//优信指导价
                'credit_info' => $credit_info,
                'vin' => $vin,
                'series_mode' => $car_loan_order_more_info['car_series_name'].' '.$car_loan_order_more_info['car_mode_name'], //刷卡时车系车型
            ];
            //dd($carInfo);
        }
        return $carInfo;
    }

    /*
     * @desc 获取vin码对应的车型
     */
    public function getVinCarInfo($applyId){
        $car_info = (new CarLoanOrderMore())->getOne('carid,guide_price,vin_json,car_mode_name', ['applyid' => $applyId]);
        $result = json_decode($car_info['vin_json']);
        $vin_car_info = (new \App\Models\NewCar\CxMode())->getOne('guideprice,modename', ['modeid' => $result->car_mode_id]);
        $xin_guide_price = (new CxModeFinance())->getOne('xin_guide_price', ['modeid' => $car_info['carid']]);
        $vin_xin_guide_price = (new CxModeFinance())->getOne('xin_guide_price', ['modeid' => $result->car_mode_id]);
        if(empty($car_info) or empty($car_info['vin_json']) or empty($xin_guide_price)){
            $date = ['status' => 1];
        }elseif($result->car_mode_id != 0){
            if($result->car_mode_id == 88888888){
                $date = [
                    'car_mode_name' => $car_info['car_mode_name'],
                    'vin_car_mode_name' => '',
                    'status' => 2,
                    'status_msg' => '无匹配车型',
                ];
                return $date;
            }
            if($car_info['car_mode_name'] == $vin_car_info['modename']){
                $date = [
                    'guide_price' => $xin_guide_price['xin_guide_price'],
                    'car_mode_name' => $car_info['car_mode_name'],
                    'vin_car_mode_name' => $vin_car_info['modename'],
                    'status_msg' =>'无',
                    'status' => 3,
                ];
            }else{
                if($xin_guide_price['xin_guide_price'] < $vin_xin_guide_price['xin_guide_price']){
                    $compare_price = '车辆车型<vin对应车型 相差'.(number_format(($vin_xin_guide_price['xin_guide_price'] - $xin_guide_price['xin_guide_price']),2)).'万';
                }else{
                    $compare_price = '车辆车型>vin对应车型 相差'.(number_format(($xin_guide_price['xin_guide_price'] - $vin_xin_guide_price['xin_guide_price']),2)).'万';
                }
                $date = [
                    'guide_price' => $xin_guide_price['xin_guide_price'],
                    'car_mode_name' => $car_info['car_mode_name'],
                    'vin_guide_price' => $vin_xin_guide_price['xin_guide_price'],
                    'vin_car_mode_name' => $vin_car_info['modename'],
                    'status_msg' => $compare_price,
                    'status' => 4,
                ];
            }
        }else{
            $date = [
                'guide_price' => $xin_guide_price['xin_guide_price'],
                'car_mode_name' => $car_info['car_mode_name'],
                'vin_car_mode_name' => '',
                'status' => 5,
                'status_msg' => '无',
            ];
        }
        return $date;
    }

    /*
     * @desc 获取二手车车辆复检状态以及免责书地址
     *
     * @param $carid int 车辆Id
     *
     * @return $recheckResult []
     */
    public function getRecheckResultOfByCarid($carid){
        $masterCarId = (new CarHalfDetail())->getOne('master_carid',['carid'=>$carid]);
        if($masterCarId == 0){
            return [];
        }
        $masterCarId = $masterCarId['master_carid'];
        $type = (new Car())->getOne('type',['carid'=>$masterCarId]);
        if($type['type'] == 1){
            $finalCarId = (new CollectCar())->getOne('c_carid',['carid'=>$masterCarId]);
            $masterCarId = $finalCarId['c_carid'];
        }else{
            $masterCarId = $carid;
        }
        $ckCarTask = (new CkCarTask())->getOne('*',['source_car_id'=>$masterCarId,'type'=>2]);
        if($ckCarTask){
            return $ckCarTask['task_status'];
        }
    }

    /**
     * 手动领取面签订单
     * @param $visaId
     * @param $type  int 判断列表详情类型(1待信审列表 ,2全量订单列表, 3复议列表)
     * @return array
     */
    public function grabOrder($visaId, $type = 1)
    {
        if (FastVisa::lockVisa($visaId)) {
            return ['code'=>-1, 'msg'=>'该订单已被领取或者重置'];
        }
        try{
            //获取session值
            $seatId = session('uinfo.seat_id');
            $seatName = session('uinfo.fullname');
            if (!$seatId) {
                throw new \Exception('登录信息错误');
            }
            //判断用户是否可领取订单
            $seatModel = new SeatManage();
            $seatInfo = $seatModel->getOne(['id','im_account_id','status','work_status'],['id'=>$seatId]);
            if ($type == 1) {
                if(!$seatInfo){
                    throw new \Exception('您不是坐席');
                }
                if(!$seatInfo['im_account_id']){
                    throw new \Exception('未注册新网IM账号');
                }
            }
            if($seatInfo['status'] != $seatModel::SEAT_STATUS_ON){
                throw new \Exception('用户被禁用');
            }
            //是否有正在处理中的订单(强制读主库)
            $visaModel = new FastVisa();
            $visaInfo = $visaModel->getOne(['id','master_id', 'seat_id','status'], ['id' => $visaId]);
            if (!$visaInfo) {
                throw new \Exception('面签不存在，visa_id:' . $visaId);
            }
            //检查面签单是否已经被其他坐席领取（放在外围检查有问题）
            if ($type == 1 && $visaInfo['seat_id'] != 0 && $visaInfo['seat_id'] != session('uinfo.seat_id') && $visaInfo['status'] == FastVisa::VISA_STATUS_HANG_QUEUEING) {
                throw new \Exception('该面签单已被其他坐席领取');
            }
            //是否有正在处理的订单
            $doingVisaId = $seatModel->getHandingVisaId($seatId);
            if(!in_array($seatInfo['work_status'],[SeatManage::SEAT_WORK_STATUS_FREE,SeatManage::SEAT_WORK_STATUS_BUSY,SeatManage::SEAT_WORK_STATUS_LEAVE]) && !$doingVisaId){
                throw new \Exception('当前用户状态不正确!');
            }
            if ($doingVisaId) {
                if ($doingVisaId != $visaInfo['id']) {
                    throw new \Exception('请先处理完当前的面签，面签序号：' . $doingVisaId);
                }
            } else {//如果缓存中没有正在处理的单子，则查询属于该坐席的状态为3，4的单子是否跟这个相等单子id相等(算是容错吧)
                $visaList = $visaModel->getAll('id', ['seat_id'=>$seatId, 'in'=>['status'=>[FastVisa::VISA_STATUS_IN_VIDEO, FastVisa::VISA_STATUS_IN_SEAT]]]);
                if ($visaList) {
                    $visaIds = array_column($visaList, 'id');
                    if (!in_array($visaInfo['id'], $visaIds) && $seatInfo['work_status'] != $seatModel::SEAT_WORK_STATUS_FREE) {
                        throw new \Exception('空闲状态下才可接单');
                    }
                }
            }
            $seatModel->updateKeepAliveKey($seatId, $visaId); //关联心跳
        } catch (\Exception $e) {
            FastVisa::unLockVisa($visaId);
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
        DB::connection('mysql.video_visa')->beginTransaction();
        try{
            $updateExtraData = [
                'seat_id' => $seatId,
                'seat_name' => $seatName,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($type == 3) {
                $updateExtraData['reconsideration_status'] = FastVisa::VISA_RECONSIDERATION_STATUS_DOING;
                $exeResult = $visaModel->updateBy($updateExtraData, ['id' => $visaId]); //更新visa
            } else {
                $exeResult = $visaModel->updateVisaStatus(FastVisa::VISA_STATUS_IN_SEAT, ['id' => $visaId], $updateExtraData); //更新visa
            }
            if (!$exeResult) {
                DB::connection('mysql.video_visa')->rollback();
                FastVisa::unLockVisa($visaId);
                return ['code'=>-1, 'msg'=>'更新面签失败'];
            }
            //领取成功，修改坐席状态为繁忙
            $seatModel->updateWorkSeatStatus(SeatManage::SEAT_WORK_STATUS_BUSY, ['id'=>$seatId]);
            //更新visa_log 审批时间时间
            $fastVisaLogModel = new FastVisaLog();
            $latestVisaLog = $fastVisaLogModel->getOne(['*'], ['visa_id'=>$visaId], ['id'=>'desc']);
            if (!$latestVisaLog) {
                FastException::throwException('数据异常，查询不到最新的fast_visa_log日志，visa_id:' . $visaId);
            }
            if ($latestVisaLog['seat_receive_time'] == 0) { //正常情况下都为0. 如果进入面签页再刷新，从待面签列表页再次点击来时不为0，此时就不要再更新该字段了
                $visaLogUpdateData['seat_receive_time'] = time();
                $visaLogUpdateData['seat_id'] = $seatId;
                $visaLogUpdateData['updated_at'] = date('Y-m-d H:i:s');
                if($latestVisaLog['match_order_type'] == 0){
                    $visaLogUpdateData['match_order_type'] = FastVisaLog::MATCH_ORDER_TYPE_MEN;
                }
                $exeResult = (new FastVisaLog())->updateVisaLog($visaLogUpdateData, ['id'=>$latestVisaLog['id']]);
                if (!$exeResult) {
                    FastException::throwException('数据异常，更新fast_visa_log失败，log_id:' . $latestVisaLog['id']);
                }
            }
            //插入fast_visa_result
            $visaResultData = [
                'visa_id' => $visaId,
                'master_id' => $visaInfo['master_id'],
                'seat_id' =>  session('uinfo.seat_id'),
                'created_at'=>date('Y-m-d H:i:s'),
                'updated_at'=>date('Y-m-d H:i:s'),
            ];
            $newResultId = (new FastVisaResult())->insertVisaResult($visaResultData);
            if (!$newResultId) {
                FastException::throwException('数据异常，插入fast_visa_result失败');
            }
            DB::connection('mysql.video_visa')->commit();
            if(config('common.is_use_new_order_apply')){
                //删除排队队列订单
                $redis_obj = new RedisCommon();
                $redis_obj->zRem(config('common.auto_apply_order_key'),$visaId);
                $redis_obj->zRem(config('common.auto_apply_seat_key'),session('uinfo.seat_id'));
                $fast_visa_rep_obj = new FastVisaRepository();
                $fast_visa_rep_obj->match_master_visa($visaInfo['master_id']);
            }
            FastVisa::unLockVisa($visaId);
        }catch (FastException $f){
            FastVisa::unLockVisa($visaId);
            DB::connection('mysql.video_visa')->rollback();
            return ['code'=>-1, 'msg'=>$f->getMessage()];
        }catch (\Exception $e) {
            FastVisa::unLockVisa($visaId);
            DB::connection('mysql.video_visa')->rollback();
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
        return ['code'=>1, 'msg'=>'操作成功'];
    }
}