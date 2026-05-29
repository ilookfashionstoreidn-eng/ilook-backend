<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');

    // Truncate downstream tables related to Cutting
    DB::table('spk_cutting_bahan')->truncate();
    DB::table('spk_cutting_bagian')->truncate();
    DB::table('spk_cutting_skus')->truncate();
    DB::table('spk_cutting_distribusi')->truncate();
    DB::table('spk_cutting_status_logs')->truncate();
    
    // Also truncate hasil cutting if we are resetting cutting
    if (Schema::hasTable('hasil_cutting_detail')) DB::table('hasil_cutting_detail')->truncate();
    if (Schema::hasTable('hasil_cutting')) DB::table('hasil_cutting')->truncate();

    // Optionally truncate Jasa and CMT to avoid orphans, but let's stick to Cutting and downstream
    if (Schema::hasTable('spk_jasa_status_log')) DB::table('spk_jasa_status_log')->truncate();
    if (Schema::hasTable('hasil_jasa')) DB::table('hasil_jasa')->truncate();
    if (Schema::hasTable('spk_jasa_warna')) DB::table('spk_jasa_warna')->truncate();
    if (Schema::hasTable('spk_jasa_pengambilan_log')) DB::table('spk_jasa_pengambilan_log')->truncate();
    if (Schema::hasTable('spk_jasa')) DB::table('spk_jasa')->truncate();

    if (Schema::hasTable('spk_cmt_warna')) DB::table('spk_cmt_warna')->truncate();
    if (Schema::hasTable('spk_cmt_items')) DB::table('spk_cmt_items')->truncate();
    if (Schema::hasTable('log_status_spk_cmt')) DB::table('log_status_spk_cmt')->truncate();
    if (Schema::hasTable('spk_cmt')) DB::table('spk_cmt')->truncate();
    
    // Finally truncate SpkCutting
    DB::table('spk_cutting')->truncate();

    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    
    echo "BERHASIL KOSONGKAN DATA";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
