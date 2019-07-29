<?php

namespace App\Models\Xin;

class BankPay extends XinModel
{
    protected $table='finance_income';
    public $timestamps=false;

    public function getCountByBankNo($bank_no = ''){
    	if (empty($bank_no)) {
    		return 0;
    	}
    	$bank_no = trim($bank_no);
    	$count = $this->where('bank_no','=',$bank_no)->count();
    	
    	return $count;
    }
    public function getFirstSwingCard($carId, $carType)
    {
        $finance_incomes = $this->where('carid', $carId)
            ->orderBy('pay_time', 'ASC')
            ->first();
        if ($carType == 0) {
            $business_code = '01';
        }
        if ($carType == 1) {
            $business_code = '10';
        }
        $res = ['bank_no' =>'', 'count' => 0];
        if(empty($finance_incomes['bank_no']) || !isset($finance_incomes['bank_no'])){
            return $res;
        }

        $firstPayInfo = $this->where('bank_no', $finance_incomes['bank_no'])
            ->where('business_code', $business_code)
            ->groupBy('carid')
            ->get()
            ->toArray();

        return ['bank_no' => $finance_incomes['bank_no'], 'count' => count($firstPayInfo)];
    }

}