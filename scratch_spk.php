<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$spks = \App\Models\SpkCutting::with(["tukangCutting", "productList"])->take(5)->get();
foreach ($spks as $spk) {
    echo $spk->id . " - " . ($spk->id_spk_cutting ?? "null") . " - Tukang: " . ($spk->tukangCutting->nama_tukang_cutting ?? "null") . " - Warna SKU: " . ($spk->productList->product_colour ?? "null") . "\n";
}

