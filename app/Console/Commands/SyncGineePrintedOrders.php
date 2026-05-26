<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsesGineeSyncLock;
use App\Services\GineeOrderService;
use Illuminate\Console\Command;

class SyncGineePrintedOrders extends Command
{
    use UsesGineeSyncLock;

    protected $signature = 'ginee:sync-printed-orders';
    protected $description = 'Sinkronisasi order Ginee yang resinya baru dicetak';

    public function __construct(private GineeOrderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithGineeSyncLock(function () {
            $result = $this->service->syncPrintedOrders();

            $this->info('Sinkronisasi PRINTED selesai:');
            $this->line("Total order diambil: {$result['totalProcessed']}");
            $this->line("Order baru masuk DB: {$result['new']}");
            $this->line("Order diupdate DB: {$result['updated']}");

            return self::SUCCESS;
        }, 300, 'ginee-hot-sync-lock');
    }
}
