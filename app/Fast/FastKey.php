<?php

namespace App\Fast;

class FastKey {

    const AUTO_ALLOT_VISA_ID_LOCK = 'fast_auto_allot_visa_id_lock_';
    const AUTO_ALLOT_SEAT_ID_LOCK = 'fast_auto_allot_seat_id_lock_';

    const SEAT_KEEP_ALIVE_TIME_KEY = 'fast_seat_kee_alive_time_';
    const CALLBACK_TO_VIDEO_TIME_KEY = 'fast_callback_to_video_time';
    const CALLBACK_TO_VIDEO_START_END_TIME_KEY = 'fast_callback_to_video_start_end_time';

    //面签单锁，项目内对面签进行操作时先加锁。
    const VISA_LOCK = 'fast_video_visa_lock';


    const LOG_QUEUE_ERP = 'redis_queue_request_log_erp_v1';
    const LOG_QUEUE_CJB = 'redis_queue_request_log_cjb_v1';
    const LOG_QUEUE_REMOTE_REQUEST = 'redis_queue_request_log_remote_request_v1';
    const LOG_QUEUE_OPERATION = 'redis_queue_request_log_operation_v1';
}