<?php
/**
 * @description  
 * @file Config.php
 * @date 2016年9月6日上午1:05:12
 * @author shenxin@xin.com
 */
return array(
    'rpc_client_config'=>array(
        'debug'=>false,
        //是否写文件日志
        'write_log'=>false,
        'log'=>array(
            //需自定义
            'dir'=>get_server_log_path().'rpc_log/',
        ),
        //授权配置
        'auth'=>array(
            //请求客户端标识码
            'client_key'=>'ux_fast_system',
            //客户端私钥
            'private_key'=>'xwBllO2tTAX9LypXJjoqa6yxmO9g4Xztlrpsg22phPAF6H1ujQannqwqzty2VfzU',
        ),
        'http'=>array(
            'connector'=>'curl',//curl swoole
            //最大连接时间 单位秒
            'max_connect_time'=>10,
            //请求的地址
            'curl'=>array(
                'url'=>'http://i.rpc.youxinjinrong.com/',
            ),
            'swoole'=>array(
                
            ),
        ),
    ),
);