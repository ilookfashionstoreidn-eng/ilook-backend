<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsesGineeSyncLock;
use App\Services\GineeOrderService;
use Illuminate\Console\Command;

class SyncGineeReadyToShipRepair extends Command
{
    use UsesGineeSyncLock;

    protected $signature = 'ginee:sync-ready-to-ship-repair
        {--hours= : Window jam last update READY_TO_SHIP yang akan disapu ulang}';

    protected $description = 'Repair sinkronisasi order Ginee READY_TO_SHIP dengan window last update yang lebih lebar';

    public function __construct(private GineeOrderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithGineeSyncLock(function () {
            try {
                $result = $this->service->syncReadyToShipRepairWindow($this->option('hours'));
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->info("Repair sync READY_TO_SHIP {$result['from']} sampai {$result['to']} selesai:");
            $this->line("Window jam: {$result['hours']}");
            $this->line("Total order diambil: {$result['totalProcessed']}");
            $this->line("Order baru masuk DB: {$result['new']}");
            $this->line("Order diupdate DB: {$result['updated']}");

            return self::SUCCESS;
        }, 5400, 'ginee-repair-sync-lock');
    }
}
