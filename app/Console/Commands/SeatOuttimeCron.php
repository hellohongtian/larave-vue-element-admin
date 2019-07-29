<?php

namespace App\Console\Commands;


use App\Repositories\Cron\SeatOuttimeRepository;
use Illuminate\Console\Command;

class SeatOuttimeCron extends BaseCommand
{
    protected $signature = 'cron:seatouttime {func}';
    protected $description = '坐席处理面签超时';
    protected $seat;

    public function __construct()
    {
        parent::__construct();
        $this->seat = new SeatOuttimeRepository();
    }

    public function handle()
    {
        $func = $this->argument('func');
        $this->{$func}();
    }
    
    public function seatOutTime(){
        $this->seat->seatOutTime();
    }
}