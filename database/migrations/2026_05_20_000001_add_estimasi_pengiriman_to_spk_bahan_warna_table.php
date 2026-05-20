<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_bahan_warna', function (Blueprint $table) {
            if (!Schema::hasColumn('spk_bahan_warna', 'estimasi_pengiriman')) {
                $table->date('estimasi_pengiriman')->nullable()->after('jumlah_rol');
            }
        });

        DB::table('spk_bahan_warna')
            ->join('spk_bahan', 'spk_bahan.id', '=', 'spk_bahan_warna.spk_bahan_id')
            ->whereNotNull('spk_bahan.estimasi_pengiriman')
            ->whereNull('spk_bahan_warna.estimasi_pengiriman')
            ->update([
                'spk_bahan_warna.estimasi_pengiriman' => DB::raw('spk_bahan.estimasi_pengiriman'),
            ]);
    }

    public function down(): void
    {
        Schema::table('spk_bahan_warna', function (Blueprint $table) {
            if (Schema::hasColumn('spk_bahan_warna', 'estimasi_pengiriman')) {
                $table->dropColumn('estimasi_pengiriman');
            }
        });
    }
};
