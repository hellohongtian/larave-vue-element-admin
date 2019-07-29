<?php

namespace App\Console\Commands;

use App\Library\Common;
use App\Library\HttpRequest;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaResult;
use App\XinApi\CommonApi;
use function foo\func;

class PushBlack extends BaseCommand
{
    protected $signature = 'pushblack {func}';
    protected $description = '推数据到黑名单收集接口';
    protected $result_obj;
    protected $visa_obj;


    public function __construct()
    {
        parent::__construct();
        $this->result_obj = new FastVisaResult();
    }

    public function handle()
    {
        $func = $this->argument('func');
        $this->{$func}();
    }

    public function push(){

        $query = $this->result_obj->selectRaw("fast_visa_result.refuse_tag,fast_visa_result.visa_id,f.apply_id,f.credit_apply_id,f.full_name,f.mobile,f.id_card_num")
            ->leftJoin('fast_visa as f','f.id','=','fast_visa_result.visa_id');
        $result =  $this->result_obj
            ->createWhere($query, [
                'is_push_black' => 0,
                'refuse_tag !=' => '',
                'visa_status' => FastVisa::VISA_STATUS_REFUSE
            ], ['fast_visa_result.id'=>'asc'])
            ->whereRaw("(  FIND_IN_SET(1,fast_visa_result.refuse_tag) or FIND_IN_SET(2,fast_visa_result.refuse_tag) or FIND_IN_SET(8,fast_visa_result.refuse_tag) or FIND_IN_SET(14,fast_visa_result.refuse_tag) or FIND_IN_SET(18,fast_visa_result.refuse_tag))")
            ->get()
            ->toArray();
        if (!empty($result)) {
            $test_arr = C('@.common.fast_test_account_id');
           foreach ($result as $key => $temp) {
                if (!empty($test_arr) && in_array($temp['visa_id'],$test_arr)) {
                    continue;
                }
                $refuse_str_arr = [];
                $refuse_arr = explode(',',$temp['refuse_tag']);
                $need_push_map = C("@.common.fast_add_black_refuse_tag");
                if(array_intersect($need_push_map,$refuse_arr)){
                    foreach ($refuse_arr as $val) {
                        if(in_array($val,$need_push_map)){
                            $refuse_str_arr[] = FastVisa::$visa_refuse_category[$val];
                        }
                    }
                }
                $params['applyid'] =  $temp['apply_id'];
                $params['credit_applyid'] =  $temp['credit_apply_id'];
                $params['black_reason'] =   implode(',',$refuse_str_arr);
                $params['fullname'] =  $temp['full_name'];
                $params['mobile'] =  $temp['mobile'];
                $params['id_card_num'] =  $temp['id_card_num'];

                try{
                    $res = CommonApi::addBlack($params);
                    if ($res['code'] == 1) {
                        $this->result_obj->updateBy(['is_push_black' => 1], ['id' => $temp['id']]);
                    }
                }catch (\Exception $e){
                    $msg = $e->getMessage();
                    echo "推送黑名单失败,visa_id->".$temp['visa_id'].",错误信息->".$msg."\n";
                }
           }
        }
    }
}