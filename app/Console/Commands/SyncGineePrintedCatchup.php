<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsesGineeSyncLock;
use App\Services\GineeOrderService;
use Illuminate\Console\Command;

class SyncGineePrintedCatchup extends Command
{
    use UsesGineeSyncLock;

    protected $signature = 'ginee:sync-printed-catchup
        {--hours= : Window jam PRINTED yang akan disapu ulang}';

    protected $description = 'Catch-up sinkronisasi order Ginee PRINTED dengan rolling window lebih lebar';

    public function __construct(private GineeOrderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithGineeSyncLock(function () {
            $result = $this->service->syncPrintedCatchup($this->option('hours'));

            $this->info("Catch-up sync PRINTED {$result['from']} sampai {$result['to']} selesai:");
            $this->line("Window jam: {$result['hours']}");
            $this->line("Total order diambil: {$result['totalProcessed']}");
            $this->line("Order baru masuk DB: {$result['new']}");
            $this->line("Order diupdate DB: {$result['updated']}");

            return self::SUCCESS;
        }, 7200, 'ginee-catchup-sync-lock');
    }
}
