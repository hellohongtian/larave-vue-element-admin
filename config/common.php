<?php
/**
 * 公共配置
 */

return [
    'upload_xin_domain' => $_SERVER['SITE_ENV'] == 'production' ? 'https://upload.xin.com' : 'http://upload.xin.com',
    'upload_xin_app' => 'xin_cp',
    'upload_xin_key' => 'rH2j3BPsxoxyzD',

    'qrcode_key' => $_SERVER['SITE_ENV'] == 'production' ? 'zDiwKmMxe7dVeELk' : 'VHHwXHE58PpEqAV4',
    'logo_url' => $_SERVER['SITE_ENV'] == 'production' ? 'https://fast.youxinjinrong.com/images/qrcodelogo.jpg' : 'https://develop.fast.ceshi.youxinjinrong.com/images/qrcodelogo.jpg',

    'vpn_app_key' => $_SERVER['SITE_ENV'] == 'production' ? 'ZDQ2ZGU4' : 'MzE2YWZh',
    'vpn_secret' => $_SERVER['SITE_ENV'] == 'production' ? '5e8ac54a1814dc3aa31e0f27fa38b3223f6c5ab3' : '6cc4713e5c15e616638f651460a4a9143674133a',
    'vpn_url' => $_SERVER['SITE_ENV'] == 'production' ? 'http://msg.xin.com/inner-ip/get-ip-position' : 'http://api.msg.test.intra-uxdata.com/inner-ip/get-ip-position',

    //全部的产品方案
    'product_scheme_redis_key' => 'fast_youxinjinrong_product_scheme_redis_key',
    //某一类型产品方案缓存前缀
    'car_type_product_scheme_redis_key' => 'car_type_fast_youxinjinrong_product_scheme_redis_key',
    //全国城市缓存key
    'city_list_redis_key' => 'fast_youxinjinrong_city_list_redis_key',

    //超融编码(有超融月供编码)
    'sfProductCode' => ['PS07', 'PS08', 'PS09', 'PS10'],

    //超融编码
    'allSfProductCode' => [
        'PS07', 'PS08', 'PS09', 'PS10', 'PS31', 'PS32' ,'PS52', 'PS54', 'PS55', 'PS56', 'PS57'
    ],

    //微众人脸识别接口地址
    'wz_face_host' => $_SERVER['SITE_ENV'] == 'testing' ? 'http://wzface_finance.finance.ceshi.youxinjinrong.com/' : 'http://finance.youxinjinrong.com/',
    //微众人脸识别 - 查询人脸结果接口
    'getWzFaceResultUrl' => 'api/wzface/face-query',
    //微众人脸识别 - 查询人脸结果接口
    'getWzFaceUpload' => 'api/wzface/face-uploadFile',

    //图片上传服务
    'file_uoload_url' => 'http://upload.xin.com/upload.php',
    'file_uoload_app' => 'finance',
    'file_uoload_key' => 'NeRKdbPNZ2hJLkza',

    //缓存坐席审批时间
    'seat_agree_time_key' => 'fast_youxinjinrong_seat_agree_time_key',
    'seat_agree_time' => 30*60,
    'seat_out_time' => 300,

    /********排队相关 start**********/

    //坐席抢单缓存key
    'seat_grab_single' => 'fast_youxinjinrong_seat_grab_single_',

    /********排队相关 end**********/

    //关系网接口
    'relation_net_url' => $_SERVER['SITE_ENV'] == 'testing' ? 'http://develop.relation.ceshi.youxinjinrong.com/relation_data/get_graph_b' : 'http://relation.youxinjinrong.com/relation_data/get_graph_b',
    'relation_count_url' => $_SERVER['SITE_ENV'] == 'testing' ? 'http://develop.relation.ceshi.youxinjinrong.com/relation_data/get_graph_user_num' : 'http://relation.youxinjinrong.com/relation_data/get_graph_user_num',
    'relation_s' => 'fast',
    'relation_secret' => $_SERVER['SITE_ENV'] == 'testing' ? '#ZC3bz*t$EZJhz*k' : 'GhOD%P14hmj@BmvG',


    //决策引擎接口
    'decision_info_url' => $_SERVER['SITE_ENV'] == 'testing' ? 'http://cash.risk.ceshi.youxinjinrong.com/apply/get_decision_data' : 'http://risk.youxinjinrong.com/apply/get_decision_data',

    //定时任务锁定key
    'fast_cron_lock' => 'fast_youxinjinrong_cron_key',
    //分单队列有序集合key
    'auto_apply_seat_key' => 'auto_apply_cron_seat_key',
    'auto_apply_order_key' => 'auto_apply_cron_order_key',

    //分单紧急配置true 采用新分单，false 采用旧分单
    'is_use_new_order_apply' => true,

    //云和-测消费能力水平
    'yunhe_consumption_power_level'=>[
        '0' => '未知',
        '1' => '统计账期内收入为 0',
        'A' => '3000 以下',
        'B' => '3000-5000',
        'C' => '5000-8000',
        'D' => '8000-12000',
        'E' => '12000-16000',
        'F' => '16000-20000',
        'G' => '20000-26000',
        'H' => '26000-40000',
        'I' => '40000-60000',
        'J' => '60000 以上'
    ],
    'seat_name_key' => 'fast_seat_and_name_key',
    #维护key
    '__maintained__' => 'fast_youxinjinrong_com__maintained__',

    'test_video_call_back' => 'http://templet_new.fast.ceshi.youxinjinrong.com/im/test_tecent_callback',
    //关系网_src
    'relation_src' => 'jr_fast',
    //靜態文件版本號
    'static_version' => 'v1.0.5',
    //订单类型队列key
    'apply_sale_type_zset_key' => [
        \App\Models\XinFinance\CarHalfService::PURCHASE_SOURCE_X1 => 'auto_apply_cron_sale_type_x3_key',
        \App\Models\XinFinance\CarHalfService::PURCHASE_SOURCE_X2 => 'auto_apply_cron_sale_type_x3_key',
        \App\Models\XinFinance\CarHalfService::PURCHASE_SOURCE_X3 => 'auto_apply_cron_sale_type_x3_key',
        \App\Models\XinFinance\CarHalfService::PURCHASE_SOURCE_COMMON => 'auto_apply_cron_sale_type_common_key',
        \App\Models\XinFinance\CarHalfService::PURCHASE_SOURCE_IM => 'auto_apply_cron_sale_type_im_key',
    ],
    //黑名单收集接口_s(http://doc.xin.com/pages/viewpage.action?pageId=12865193)
    'fast_add_black_s' => is_production_env() ? 'zgQdVdA0q!QF090^' : 'QhZ7E$CZTrBs7%68',
    //测试账号面签id
    'fast_test_account_id' => [15],
    //需要推黑名单收集的拒绝码
    'fast_add_black_refuse_tag' => [
        \App\Models\VideoVisa\FastVisa::VISA_REFUSE_CATEGORY_D_DG,
        \App\Models\VideoVisa\FastVisa::VISA_REFUSE_CATEGORY_D_FD ,
        \App\Models\VideoVisa\FastVisa::VISA_REFUSE_CATEGORY_D_QZ ,
        \App\Models\VideoVisa\FastVisa::VISA_REFUSE_CATEGORY_D_XJ2 ,
        \App\Models\VideoVisa\FastVisa::VISA_REFUSE_CATEGORY_D_ID2 ,
    ],
];