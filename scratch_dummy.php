<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t = \App\Models\TukangCutting::firstOrCreate(["nama_tukang_cutting" => "ERIK"]);
$pL = \App\Models\ProductList::firstOrCreate(["product_group" => "SET BANGWOOL", "product_size" => "L"], ["product_source" => "SET BANGWOOL L", "product_colour" => "DUSTY", "product" => "BANGWOOL", "estimasi_cutting" => 60, "estimasi_qty" => 30]);
$pXL = \App\Models\ProductList::firstOrCreate(["product_group" => "SET BANGWOOL", "product_size" => "XL"], ["product_source" => "SET BANGWOOL XL", "product_colour" => "DUSTY", "product" => "BANGWOOL", "estimasi_cutting" => 60, "estimasi_qty" => 30]);

$b1 = \App\Models\Bahan::firstOrCreate(["nama_bahan" => "BANGWOOL", "group_bahan" => "SET BANGWOOL"]);
$b2 = \App\Models\Bahan::firstOrCreate(["nama_bahan" => "BABY TERRY PICKY", "group_bahan" => "BABY TERRY PICKY"]);

$spk = \App\Models\SpkCutting::create([
    "id_spk_cutting" => "3252",
    "tukang_cutting_id" => $t->id,
    "product_list_id" => $pL->id,
    "jumlah_asumsi_produk" => 60,
    "tanggal_batas_kirim" => "2026-06-24",
    "status_cutting" => "belum_diambil",
    "pic" => "BELUM POTONG",
    "harga_jasa" => 0,
    "harga_per_pcs" => 0,
    "satuan_harga" => "Pcs"
]);

$bag1 = \App\Models\SpkCuttingBagian::create(["spk_cutting_id" => $spk->id, "nama_bagian" => "Utama"]);
$bag2 = \App\Models\SpkCuttingBagian::create(["spk_cutting_id" => $spk->id, "nama_bagian" => "Kombinasi"]);

$bah1 = \App\Models\SpkCuttingBahan::create(["spk_cutting_bagian_id" => $bag1->id, "bahan_id" => $b1->id, "sumber_komponen" => "bahan", "warna" => "DUSTY", "qty" => 1]);
$bah2 = \App\Models\SpkCuttingBahan::create(["spk_cutting_bagian_id" => $bag2->id, "bahan_id" => $b2->id, "sumber_komponen" => "bahan", "warna" => "DUSTY", "qty" => 1]);

$bah1->skus()->attach([$pL->id => ["qty" => 0.5], $pXL->id => ["qty" => 0.5]]);
$bah2->skus()->attach([$pL->id => ["qty" => 0.5], $pXL->id => ["qty" => 0.5]]);

echo "Dummy created! SPK ID: " . $spk->id;

