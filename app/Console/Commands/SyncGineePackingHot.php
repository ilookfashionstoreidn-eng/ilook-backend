<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsesGineeSyncLock;
use App\Services\GineeOrderService;
use Illuminate\Console\Command;

class SyncGineePackingHot extends Command
{
    use UsesGineeSyncLock;

    protected $signature = 'ginee:sync-packing-hot';
    protected $description = 'Sinkronisasi cepat order Ginee untuk kebutuhan packing';

    public function __construct(private GineeOrderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithGineeSyncLock(function () {
            $result = $this->service->syncPackingHot();

            $this->info('Hot sync packing selesai:');
            $this->line("Total order diambil: {$result['totalProcessed']}");
            $this->line("Order baru masuk DB: {$result['new']}");
            $this->line("Order diupdate DB: {$result['updated']}");

            foreach ($result['details'] as $label => $detail) {
                $this->line(
                    "{$label}: ambil {$detail['totalProcessed']}, baru {$detail['new']}, update {$detail['updated']}"
                );
            }

            return self::SUCCESS;
        }, 300, 'ginee-hot-sync-lock');
    }
}
