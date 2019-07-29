<?php
/**
 * @description  开发环境RPC配置
 * @file Config.php
 * @date 2016年9月6日上午1:05:12
 * @author shenxin@xin.com
 */
return array(
    'rpc_client_config'=>array(
        'debug'=>false,
        //是否写文件日志
        'write_log'=>true,
        'log'=>array(
            //需自定义
            'dir'=>get_server_log_path().'rpc_log/',
        ),
        //授权配置
        'auth'=>array(
            //请求客户端标识码
            'client_key'=>'vpd83wlzeqf7qglpl94e2z99g5jdlalk',
            //客户端私钥
            'private_key'=>'NUa8xpIxzkuVeTPrRHaLUQavciwmg8XJhKw68pNg6YlNejeMCMspQghBZDuslN4n',
        ),
        'http'=>array(
            'connector'=>'curl',//curl swoole
            //最大连接时间 单位秒
            'max_connect_time'=>60,
            //请求的地址
            'curl'=>array(
                'url'=>is_production_env() ? 'http://i.rpc.youxinjinrong.com/' : 'http://rpc.ceshi.youxinjinrong.com/',
            ),
            'swoole'=>array(
                '127.0.0.1:3298',
                '127.0.0.1:3298',
                '127.0.0.1:3298',
            ),
        ),
    ),
);