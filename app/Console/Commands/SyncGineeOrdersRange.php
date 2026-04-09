<?php

namespace App\Console\Commands;

use App\Services\GineeOrderService;
use Illuminate\Console\Command;

class SyncGineeOrdersRange extends Command
{
    protected $signature = 'ginee:sync-range {from : Tanggal mulai, format YYYY-MM-DD} {to? : Tanggal akhir, format YYYY-MM-DD}';
    protected $description = 'Sinkronisasi order Ginee berdasarkan rentang tanggal order';

    public function __construct(private GineeOrderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->syncCreateDateRange(
                $this->argument('from'),
                $this->argument('to')
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sinkronisasi rentang {$result['from']} sampai {$result['to']} selesai:");
        $this->line("Total order diambil: {$result['totalProcessed']}");
        $this->line("Order baru masuk DB: {$result['new']}");
        $this->line("Order diupdate DB: {$result['updated']}");

        return self::SUCCESS;
    }
}
