<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GineeOrderService;

class GineeFirstSyncOrders extends Command
{
    protected $signature = 'ginee:sync-first';
    protected $description = 'First sync 30 hari data';

    public function handle()
    {
        (new GineeOrderService)->syncFirstTime();
        $this->info('First sync selesai!');
    }
}
