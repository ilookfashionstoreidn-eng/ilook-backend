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

        // Catch-up: jaring order PRINTED yang lolos dari hot sync (window 12 jam)
        $schedule->command('ginee:sync-printed-catchup')->everyThirtyMinutes()->withoutOverlapping(120);

        // Repair: jaring READY_TO_SHIP yang lolos dalam 12 jam terakhir
        $schedule->command('ginee:sync-ready-to-ship-repair')->everyThirtyMinutes()->withoutOverlapping(120);

        // Health check: deteksi sync mati dalam 15 menit
        $schedule->command('ginee:health-check')->everyFifteenMinutes();

        $schedule->command('spk-cmt:auto-release-pending')->daily();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
