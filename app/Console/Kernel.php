<?php

namespace App\Console;

use App\Library\Common;
use App\Library\Helper;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Exception;
use Throwable;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     * 注：此处是引入我们新创建的类。由于我们此处是使用命令名来操作的，所以没用上这个类名。不过还是引入比较标准
     * 可以使用 command 方法通过命令名或类来调度一个 Artisan 命令：
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SeatOuttimeCron::class,
        \App\Console\Commands\ApplyQuqeCron::class,
        \App\Console\Commands\TestCommand::class,
        \App\Console\Commands\VideoCommand::class,
        \App\Console\Commands\AsyncLogToDB::class,
        \App\Console\Commands\UpdateCreditStatus::class,
        \App\Console\Commands\AutoApplyCron::class,
        \App\Console\Commands\KillProcess::class,
        \App\Console\Commands\AdminToSeatmanager::class,
        \App\Console\Commands\CreateSeatReport::class,
        \App\Console\Commands\SyncIdcardAndVin::class,
        \App\Console\Commands\SyncCreditId::class,
        \App\Console\Commands\SyncOldData::class,
        \App\Console\Commands\PushBlack::class,
    ];

    /**
     * Define the application's command schedule.
     * 注：具体的调度方法
     * 1、这个方法按照自己的需求，确定定时方法的执行顺序。通过after，before等关键词来控制
     * 2、此处相当于规定同意的定时执行时间，如都在0:30分执行下面的几个定时任务
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $this->runDailyScript($schedule);
        $this->runHourlyScript($schedule);
        $this->runMinuteScript($schedule);
    }

    /**
     * 中央面签 -- 按小时执行的脚本
     */
    private function runHourlyScript(Schedule $schedule)
    {

    }

    /**
     * 中央面签 -- 按天执行的脚本
     */
    private function runDailyScript(Schedule $schedule)
    {
    }

    /**
     * 中央面签 -- 按分钟执行的脚本
     */
    private function runMinuteScript(Schedule $schedule)
    {
        //自动分单脚本
//        $schedule->command('autoapplycron matchQueue')->runInBackground()->everyMinute();
    }

    /**
     * Register the Closure based commands for the application.
     * 这个部分是laravel自动生成的，引入我们生成的命令文件
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }

    public function handle($input, $output = null)
    {
        try {
            $this->bootstrap();

            if (! $this->commandsLoaded) {
                $this->commands();
                $this->commandsLoaded = true;
            }

            return $this->getArtisan()->run($input, $output);
        } catch (Exception $e) {
            $this->reportException($e);
            $this->renderException($output, $e);
            $this->sendErrorMail($e);
            return 1;
        } catch (Throwable $e) {
            $e = new FatalThrowableError($e);

            $this->reportException($e);
            $this->renderException($output, $e);
            $this->sendErrorMail($e);

            return 1;
        }
    }

    private function sendErrorMail($e)
    {
        $content = [
            'errorClass' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'msg' => $e->getMessage(),
            'traceString' => $e->getTraceAsString(),
        ];
        Common::sendMail('脚本报错', print_r($content, true));
    }
}
