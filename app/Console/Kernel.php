<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

  protected function schedule(Schedule $schedule)
{
    $schedule->command('ginee:sync-orders')->everyFiveMinutes()->withoutOverlapping(60);
    $schedule->command('ginee:sync-daily-repair')->dailyAt('02:17')->withoutOverlapping(360);
    $schedule->command('spk-cmt:auto-release-pending')->daily();

}

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
