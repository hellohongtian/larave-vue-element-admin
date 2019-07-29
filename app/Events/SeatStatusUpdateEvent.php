<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Models\VideoVisa\SeatManage;

class SeatStatusUpdateEvent
{
    use InteractsWithSockets, SerializesModels;

    public $seatManage;
    public $work_status_new;
    public $seat_id;
    /**
     * Create a new event instance.
     *
     * @return void
     */
//    public function __construct(SeatManage $seatManage)
    public function __construct($seat_id, $work_status_new)
    {
        $this->seat_id = $seat_id;
        $this->work_status_new = $work_status_new;
        //$this->seatManage = $seatManage;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
