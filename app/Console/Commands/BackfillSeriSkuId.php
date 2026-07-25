<?php

namespace App\Console\Commands;

use App\Models\Seri;
use App\Models\Sku;
use Illuminate\Console\Command;

class BackfillSeriSkuId extends Command
{
    protected $signature = 'seri:backfill-sku-id
                            {--dry-run : Tampilkan ringkasan tanpa menyimpan perubahan}';

    protected $description = 'Isi seri.sku_id untuk baris lama (dari sebelum kolom ini ada) via EXACT match ke tabel skus — tidak pernah menebak/fuzzy, sama seperti resolusi di SeriController::store()';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $matched = 0;
        $created = 0;
        $skipped = 0;

        Seri::whereNull('sku_id')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use ($dryRun, &$matched, &$created, &$skipped) {
                foreach ($chunk as $seri) {
                    $skuText = trim((string) $seri->sku);
                    if ($skuText === '') {
                        $skipped++;
                        continue;
                    }

                    $skuModel = Sku::where('sku', $skuText)->first();
                    if ($skuModel) {
                        $matched++;
                    } else {
                        $skuModel = null;
                        $created++;
                    }

                    if ($dryRun) {
                        continue;
                    }

                    if (!$skuModel) {
                        $skuModel = Sku::create(['sku' => $skuText, 'is_active' => true]);
                    }

                    $seri->sku_id = $skuModel->id;
                    $seri->save();
                }
            });

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Cocok ke Sku yang sudah ada: {$matched}");
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Sku baru dibuat (exact text, tidak ada yang cocok): {$created}");
        if ($skipped > 0) {
            $this->warn("Dilewati (sku kosong/blank): {$skipped}");
        }

        return self::SUCCESS;
    }
}
