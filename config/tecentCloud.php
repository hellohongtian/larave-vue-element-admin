<?php

$get_app_keys = array(
    'class_belong' => 'audioVideoRepository',
    'allow_method' => ['GET'],
    'middleware' => ['crossDomain'],
    'params' => [
        'app_name' => 'required|string',
    ],
    'callback_func' => 'getKeysByAppName'
);

$get_user_sig = array(
    'class_belong' => 'audioVideoRepository',
    'allow_method' => ['GET'],
    'middleware' => ['crossDomain'],
    'params' => [
        'app_name' => 'required|string',
        'user_id' => 'required|string',
        'expire' => 'integer|max:18000|min:300'
    ],
    'callback_func' => 'getUserSig'
);

$get_record_video = array(
    'class_belong' => 'audioVideoRepository',
    'allow_method' => ['GET'],
    'middleware' => ['crossDomain'],
    'params' => [
        'channel_id' => 'required|string'
    ],
    'callback_func' => 'getRecordVideo'
);

$apply_mix_stream = array(
    'class_belong' => 'audioVideoRepository',
    'allow_method' => ['GET'],
    'middleware' => ['crossDomain'],
    'params' => [
        'user_one_id' => 'required|string',
        'user_two_id' => 'required|string',
        'room_id' => 'required|string'
    ],
    'callback_func' => 'applyMixStream'
);

return array(
    "get_app_keys" => $get_app_keys,
    "get_user_sig" => $get_user_sig,
    "get_record_video" => $get_record_video,
    "apply_mix_stream" => $apply_mix_stream,
);






