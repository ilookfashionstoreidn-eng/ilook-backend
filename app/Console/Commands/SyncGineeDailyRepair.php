<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsesGineeSyncLock;
use App\Services\GineeOrderService;
use Illuminate\Console\Command;

class SyncGineeDailyRepair extends Command
{
    use UsesGineeSyncLock;

    protected $signature = 'ginee:sync-daily-repair {days? : Jumlah hari create date yang akan direpair}';
    protected $description = 'Repair sync harian order Ginee dengan window create date yang lebih lebar';

    public function __construct(private GineeOrderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runWithGineeSyncLock(function () {
            try {
                $result = $this->service->syncDailyRepairWindow($this->argument('days'));
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->info("Repair sync harian {$result['from']} sampai {$result['to']} selesai:");
            $this->line("Window hari: {$result['days']}");
            $this->line("Total order diambil: {$result['totalProcessed']}");
            $this->line("Order baru masuk DB: {$result['new']}");
            $this->line("Order diupdate DB: {$result['updated']}");

            return self::SUCCESS;
        }, 21600, 'ginee-repair-sync-lock');
    }
}
