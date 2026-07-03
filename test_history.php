<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $res = App\Models\HasilCutting::with([
        'spkCutting:id,id_spk_cutting,produk_id',
        'spkCutting.produk:id,nama_produk,gambar_produk',
        'bahan.spkCuttingBahan.bagian',
        'bahan.spkCuttingBahan.bahan'
    ])->orderByDesc('created_at')->get();
    echo "SUCCESS count:" . count($res) . "\n";

    // Test formatting loop
    $grouped = $res->groupBy(function ($item) {
        return optional($item->spkCutting)->produk_id ?? 'unknown';
    });

    foreach ($grouped as $produkId => $items) {
        $produk = optional($items->first()->spkCutting)->produk;
        $items->map(function ($item) {
            $statusAgregat = $item->status_perbandingan_agregat;
            if (is_string($statusAgregat)) {
                $statusAgregat = json_decode($statusAgregat, true);
            }
            if (!is_array($statusAgregat)) {
                $statusAgregat = [];
            }
            $item->bahan->map(function ($bahan) use ($statusAgregat) {
                $spkBahan = $bahan->spkCuttingBahan;
                $status = null;
                if ($spkBahan && $spkBahan->warna) {
                    $found = collect($statusAgregat)->firstWhere('warna', $spkBahan->warna);
                    $status = $found['status'] ?? null;
                }
                return [
                    'nama_bagian' => optional(optional($spkBahan)->bagian)->nama_bagian,
                    'nama_bahan' => optional(optional($spkBahan)->bahan)->nama_bahan,
                ];
            });
        });
    }
    echo "LOOP SUCCESS\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "TRACE: " . $e->getTraceAsString() . "\n";
}
