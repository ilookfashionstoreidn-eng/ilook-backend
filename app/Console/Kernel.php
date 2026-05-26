<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        // Hot sync stays independent. READY pools share one lock so pool jobs never run double.
        $schedule->command('ginee:sync-packing-hot')->cron('*/2 * * * *')->withoutOverlapping(10);
        $schedule->command('ginee:sync-ready-to-ship-pool --days=3')->cron('3,9,15,21,25,37,43,49,55 * * * *')->withoutOverlapping(30);
        $schedule->command('ginee:sync-ready-to-ship-pool --days=30')->hourlyAt(31)->withoutOverlapping(120);
        $schedule->command('ginee:sync-daily-repair 90')->dailyAt('02:17')->withoutOverlapping(360);
        $schedule->command('spk-cmt:auto-release-pending')->daily();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
