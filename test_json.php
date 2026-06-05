<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$r = \App\Models\StokBahanKeluar::first();
if ($r) {
    echo json_encode(\App\Models\PembelianBahanRol::with('stokBahan')->where('barcode', $r->barcode)->first());
} else {
    echo 'no stok_bahan_keluar';
}
