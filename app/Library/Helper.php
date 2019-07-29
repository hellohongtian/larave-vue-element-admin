<?php
/**
 * 通用类
 */

namespace App\Library;


class Helper
{

    /**
     * @return bool  //判断是否是线上环境
     */
    public static function isProduction()
    {
        if ($_SERVER['SITE_ENV'] == 'production') {
            return true;
        }
        return false;
    }

    //格式化金额
    public static function number_format_money($money) {
        if (empty($money)) {
            return number_format(0, 2);
        }
        return number_format($money / 100, 2);
    }

    /**
     * 上传图片到图片服务器
     * @param $base64FileData
     * @return array 如果成功 result=true,data=上传后的图片地址; 如果失败，result = false, msg 为失败原因
     */
    public static function uploadPic($base64FileData)
    {
        $result = [
            'result' => true,
            'pic' => '',
            'msg' => '',
        ];

        try {
            //图片上传
            $files = base64_decode($base64FileData);
            if (!$files) {
                throw new \Exception('图片不能解析');
            }

            $url = config('common.file_uoload_url');
            $app = config('common.file_uoload_app');
            $key = config('common.file_uoload_key');
            $params['app'] = $app;
            $params['key'] = $key;
            $params['method'] = 'buf';
            $params['pic'] = $files;

            $ret = HttpRequest::postJson($url, $params);

            if (!isset($ret['code']) || $ret['code'] != 1) {
                throw new \Exception('图片上传错误,' . (isset($ret['msg']) ? $ret['msg'] : '上传失败'));
            }

            $result['pic'] = $ret['pic'];
        } catch (\Exception $e) {
            $result['result'] = false;
            $result['msg'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * 根据时间区间，给出左闭右开的每一天
    */
    public static function dates_between($start_date, $end_date)
    {
        $start_date = Date('Y-m-d',strtotime($start_date));
        $end_date = Date('Y-m-d',strtotime($end_date));
        $date = array();
        $current_date = $start_date;

        while ($current_date <= $end_date) {
            $date[] = $current_date;
            $current_date = date("Y-m-d", strtotime("+1 day", strtotime($current_date)));
        }
        return $date;
    }

}