<?php
namespace App\Console\Commands;

use App\Models\VideoVisa\ErrorCodeLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Artisan;

class TestCommand extends BaseCommand
{
    protected $signature = 'TestCommand {func} ';
    protected $description = '执行一次的脚本';

    public function handle()
    {
        $func = $this->argument('func');
        $this->{$func}();
    }

    private function test()
    {
        echo 111;
        (new ErrorCodeLog())->runLog(2345, 'test zz command', 2345);
    }

    private function trz()
    {
        $this->isCommandRunning('applyQueue', 'manageQueue');
    }
}