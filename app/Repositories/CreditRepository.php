<?php
/**
 * 征信
 */
namespace App\Repositories;

use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\SeatManage;
use Illuminate\Support\Facades\DB;
use App\Library\Common;
use App\XinApi\CreditApi;
use App\Models\Xin\CarHalfApply;


class CreditRepository
{
     public function getCreditDetailByApplyId($applyId){
         if(empty($applyId)){
             return false;
         }

         $creditApi = new CreditApi();
         $result = $creditApi->getCreditDetail($applyId);
         if($result['code'] != 1){
             return false;
         }
         $data = $this->formatCreditData($result['data']);
         return $data;

     }

     public function formatCreditData($data){
         $return['webank'] = isset($data['webank']) ? $data['webank'] : [];
         $return['czbank'] = isset($data['czbank']) ? $data['czbank'] : [];
         $return['xwbank'] = isset($data['xwbank']) ? $data['xwbank'] : [];

         return $return;
     }
}
