<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GineeOrderService;
use App\Console\Commands\Concerns\UsesGineeSyncLock;

class GineeFirstSyncOrders extends Command
{
    use UsesGineeSyncLock;

    protected $signature = 'ginee:sync-first';
    protected $description = 'First sync 30 hari data';

    public function __construct(private GineeOrderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithGineeSyncLock(function () {
            $this->service->syncFirstTime();
            $this->info('First sync selesai!');

            return self::SUCCESS;
        }, 43200);
    }
}
