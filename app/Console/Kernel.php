<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        // Packing hot sync has its own lock so repair jobs cannot delay packing data.
        $schedule->command('ginee:sync-packing-hot')->cron('*/2 * * * *')->withoutOverlapping(10);
        $schedule->command('ginee:sync-printed-catchup')->cron('7,21,37,51 * * * *')->withoutOverlapping(120);
        $schedule->command('ginee:sync-daily-repair 7')->hourlyAt(43)->withoutOverlapping(180);
        $schedule->command('ginee:sync-daily-repair 90')->dailyAt('02:17')->withoutOverlapping(360);
        $schedule->command('spk-cmt:auto-release-pending')->daily();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
