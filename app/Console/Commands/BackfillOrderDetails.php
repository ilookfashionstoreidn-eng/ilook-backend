<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\GineeOrderService;
use Illuminate\Console\Command;

class BackfillOrderDetails extends Command
{
    protected $signature = 'ginee:backfill-order-details
                            {--chunk=20 : Jumlah order per batch ke Ginee API}
                            {--limit= : Batasi total order yang diproses (opsional, untuk testing)}
                            {--dry-run : Tampilkan jumlah order yang akan diproses tanpa eksekusi}';

    protected $description = 'Backfill detail order (alamat, biaya kirim, dll.) untuk order yang sudah ada di DB dengan field kosong';

    public function __construct(private GineeOrderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        $chunkSize = max(1, min((int) $this->option('chunk'), 50));
        $limit     = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun    = $this->option('dry-run');

        // Query: order yang punya ginee_order_id tapi detail extended masih null
        $query = Order::whereNotNull('ginee_order_id')
            ->where(function ($q) {
                $q->whereNull('customer_address')
                  ->orWhereNull('logistic_provider_name')
                  ->orWhereNull('shipping_fee');
            })
            ->orderBy('id');

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('✅ Semua order sudah memiliki detail lengkap. Tidak ada yang perlu dibackfill.');
            return self::SUCCESS;
        }

        if ($limit !== null) {
            $totalCount = min($totalCount, $limit);
        }

        $this->info("📦 Order yang akan dibackfill: {$totalCount}");

        if ($dryRun) {
            $this->warn('Mode --dry-run aktif. Tidak ada data yang diubah.');
            return self::SUCCESS;
        }

        if (!$this->confirm("Lanjutkan backfill {$totalCount} order?", true)) {
            $this->line('Dibatalkan.');
            return self::SUCCESS;
        }

        $processed  = 0;
        $newCount   = 0;
        $updated    = 0;
        $errors     = 0;
        $offset     = 0;

        $bar = $this->output->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Memulai...');
        $bar->start();

        do {
            $batchQuery = (clone $query)->offset($offset)->limit($chunkSize);

            if ($limit !== null) {
                $remaining = $limit - $processed;
                if ($remaining <= 0) break;
                $batchQuery->limit(min($chunkSize, $remaining));
            }

            $orders = $batchQuery->get(['id', 'ginee_order_id', 'order_number']);

            if ($orders->isEmpty()) break;

            $gineeOrderIds = $orders->pluck('ginee_order_id')->filter()->unique()->values()->toArray();

            if (!empty($gineeOrderIds)) {
                try {
                    $bar->setMessage("Syncing batch ginee_order_id: " . implode(', ', array_slice($gineeOrderIds, 0, 3)) . (count($gineeOrderIds) > 3 ? '...' : ''));

                    $result = $this->service->syncOrderByIds($gineeOrderIds, 'backfill');

                    $newCount += $result['new']     ?? 0;
                    $updated  += $result['updated'] ?? 0;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("❌ Error pada batch (offset {$offset}): " . $e->getMessage());
                }
            }

            $batchCount  = $orders->count();
            $processed  += $batchCount;
            $offset     += $batchCount;
            $bar->advance($batchCount);

        } while ($orders->count() === $chunkSize && ($limit === null || $processed < $limit));

        $bar->setMessage('Selesai!');
        $bar->finish();

        $this->newLine(2);
        $this->info("✅ Backfill selesai.");
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total diproses',    $processed],
                ['Order baru (sync)', $newCount],
                ['Order diupdate',    $updated],
                ['Batch error',       $errors],
            ]
        );

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
