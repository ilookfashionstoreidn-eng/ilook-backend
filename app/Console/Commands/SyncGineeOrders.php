<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GineeOrderService;
use App\Console\Commands\Concerns\UsesGineeSyncLock;

class SyncGineeOrders extends Command
{
    use UsesGineeSyncLock;

    protected $signature = 'ginee:sync-orders';
    protected $description = 'Sinkronisasi order terbaru dari Ginee ke DB lokal';

    protected $service;

    public function __construct(GineeOrderService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        return $this->runWithGineeSyncLock(function () {
            $result = $this->service->syncRecentOrders();

            $this->info("Sinkronisasi selesai:");
            $this->line("Total order diambil: {$result['totalProcessed']}");
            $this->line("Order baru masuk DB: {$result['new']}");
            $this->line("Order diupdate DB: {$result['updated']}");

            return self::SUCCESS;
        }, 300, 'ginee-hot-sync-lock');
    }
}
