<?php

namespace App\Listeners;

use App\Events\SeatStatusUpdateEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\VideoVisa\SeatManagerLog;
use App\Repositories\SeatManageRepository;
use Log;

class SeatStatusUpdateListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  SeatStatusUpdateEvent  $event
     * @return mixed
     */
    public function handle(SeatStatusUpdateEvent $event)
    {
        //记录时间日志
        $result = (new SeatManageRepository())->upSeatStatusLog($event->seat_id,$event->work_status_new);
//        if(is_production_env()) {
//            Log::info('保存成功！', ['seat_id' => $event->seat_id, 'work_status' => $event->work_status_new]);
//        }
        return $result;
    }
}
