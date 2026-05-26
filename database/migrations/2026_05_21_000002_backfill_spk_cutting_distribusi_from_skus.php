<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $sizeOrder = [
        'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'XXXXL', 'ALL SIZE', 'FREE SIZE',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('spk_cutting')
            ->select(['id', 'id_spk_cutting'])
            ->orderBy('id')
            ->chunkById(100, function ($spks) use ($now) {
                foreach ($spks as $spk) {
                    $skus = DB::table('spk_cutting_skus')
                        ->join('product_lists', 'spk_cutting_skus.product_list_id', '=', 'product_lists.id')
                        ->where('spk_cutting_skus.spk_cutting_id', $spk->id)
                        ->whereNotNull('spk_cutting_skus.product_list_id')
                        ->select([
                            'product_lists.id',
                            'product_lists.sku_name',
                            'product_lists.product_colour',
                            'product_lists.product_size',
                        ])
                        ->get()
                        ->sort(function ($a, $b) {
                            $sizeCompare = $this->sizeRank($a->product_size) <=> $this->sizeRank($b->product_size);
                            if ($sizeCompare !== 0) {
                                return $sizeCompare;
                            }

                            $colorCompare = strcmp((string) $a->product_colour, (string) $b->product_colour);
                            if ($colorCompare !== 0) {
                                return $colorCompare;
                            }

                            return strcmp((string) $a->sku_name, (string) $b->sku_name);
                        })
                        ->values();

                    foreach ($skus as $index => $sku) {
                        $kodeSeri = $spk->id_spk_cutting . $this->suffix($index);
                        $distribusi = DB::table('spk_cutting_distribusi')
                            ->where('spk_cutting_id', $spk->id)
                            ->where('kode_seri', $kodeSeri)
                            ->first();

                        if (!$distribusi) {
                            $distribusiId = DB::table('spk_cutting_distribusi')->insertGetId([
                                'spk_cutting_id' => $spk->id,
                                'hasil_cutting_id' => null,
                                'kode_seri' => $kodeSeri,
                                'jumlah_produk' => 0,
                                'status' => 'draft',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        } else {
                            $distribusiId = $distribusi->id;
                        }

                        $hasDetail = DB::table('spk_cutting_distribusi_detail')
                            ->where('spk_cutting_distribusi_id', $distribusiId)
                            ->exists();

                        if (!$hasDetail) {
                            DB::table('spk_cutting_distribusi_detail')->insert([
                                'spk_cutting_distribusi_id' => $distribusiId,
                                'warna' => $sku->product_colour ?: '-',
                                'jumlah_produk' => 0,
                                'produk_sku_id' => null,
                                'product_list_id' => $sku->id,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        // Data backfill is intentionally kept to avoid deleting distributions that may already be used.
    }

    private function sizeRank(?string $size): int
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', (string) $size))));
        $normalized = $normalized === 'ALLSIZE' ? 'ALL SIZE' : $normalized;
        $normalized = $normalized === 'FREESIZE' ? 'FREE SIZE' : $normalized;
        $index = array_search($normalized, $this->sizeOrder, true);

        return $index === false ? count($this->sizeOrder) : $index;
    }

    private function suffix(int $index): string
    {
        $alphabet = range('A', 'Z');
        $suffix = '';
        $number = $index;

        do {
            $suffix = $alphabet[$number % 26] . $suffix;
            $number = intdiv($number, 26) - 1;
        } while ($number >= 0);

        return $suffix;
    }
};
