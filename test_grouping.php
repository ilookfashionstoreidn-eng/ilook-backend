<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$hasilCutting = App\Models\HasilCutting::with([
    'spkCutting:id,id_spk_cutting,produk_id,product_list_id',
    'spkCutting.produk:id,nama_produk,gambar_produk',
    'spkCutting.productList:id,sku_name,product_list_image_id',
    'spkCutting.productList.productListImage:id,image_path',
])->orderByDesc('created_at')->get();

$grouped = $hasilCutting->groupBy(function ($item) {
    $spk = optional($item->spkCutting);
    if ($spk->product_list_id) {
        return 'PL-' . $spk->product_list_id;
    }
    if ($spk->produk_id) {
        return 'PR-' . $spk->produk_id;
    }
    return 'unknown';
});

$unknowns = $grouped->get('unknown');
echo "Number of unknowns: " . ($unknowns ? $unknowns->count() : 0) . "\n";

foreach ($grouped as $groupId => $items) {
    $spk = optional($items->first()->spkCutting);
    $namaProduk = null;
    $gambarUrl = null;
    $displayId = $groupId;

    if (str_starts_with($groupId, 'PL-')) {
        $productList = $spk->productList;
        if ($productList) {
            $namaProduk = $productList->sku_name;
            $displayId = $productList->id;
        }
    } elseif (str_starts_with($groupId, 'PR-')) {
        $produk = $spk->produk;
        if ($produk) {
            $namaProduk = $produk->nama_produk;
            $displayId = $produk->id;
        }
    } else {
        $displayId = 'unknown';
    }

    echo "Group: " . $groupId . " | Display ID: " . $displayId . " | Nama: " . $namaProduk . " | Count: " . $items->count() . "\n";
}
