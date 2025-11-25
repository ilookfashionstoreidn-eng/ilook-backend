<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GineeOrderService;

class SyncGineeOrders extends Command
{
    protected $signature = 'ginee:sync-orders';
    protected $description = 'Sinkronisasi order terbaru dari Ginee ke DB lokal';

    protected $service;

    public function __construct(GineeOrderService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $result = $this->service->syncRecentOrders();

        $this->info("Sinkronisasi selesai:");
        $this->line("Total order diambil: {$result['totalProcessed']}");
        $this->line("Order baru masuk DB: {$result['new']}");
        $this->line("Order diupdate DB: {$result['updated']}");
    }
}
