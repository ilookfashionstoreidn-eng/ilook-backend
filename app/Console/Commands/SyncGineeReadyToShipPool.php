<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsesGineeSyncLock;
use App\Services\GineeOrderService;
use Illuminate\Console\Command;

class SyncGineeReadyToShipPool extends Command
{
    use UsesGineeSyncLock;

    protected $signature = 'ginee:sync-ready-to-ship-pool
        {--days=3 : Window hari create date READY_TO_SHIP yang akan disapu}';

    protected $description = 'Sinkronisasi pool order Ginee READY_TO_SHIP berdasarkan create date';

    public function __construct(private GineeOrderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithGineeSyncLock(function () {
            $result = $this->service->syncReadyToShipCreateDatePool($this->option('days'));

            $this->info("READY_TO_SHIP pool {$result['from']} sampai {$result['to']} selesai:");
            $this->line("Window hari: {$result['days']}");
            $this->line("Total order diambil: {$result['totalProcessed']}");
            $this->line("Order baru masuk DB: {$result['new']}");
            $this->line("Order diupdate DB: {$result['updated']}");

            return self::SUCCESS;
        }, 7200, 'ginee-active-pool-lock');
    }
}
