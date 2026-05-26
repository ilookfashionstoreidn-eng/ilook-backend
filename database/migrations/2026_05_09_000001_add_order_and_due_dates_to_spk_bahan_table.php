<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_bahan', function (Blueprint $table) {
            if (!Schema::hasColumn('spk_bahan', 'tanggal_pemesanan')) {
                $table->date('tanggal_pemesanan')->nullable()->after('tanggal_pembayaran');
            }

            if (!Schema::hasColumn('spk_bahan', 'tanggal_jatuh_tempo')) {
                $table->date('tanggal_jatuh_tempo')->nullable()->after('tanggal_pemesanan');
            }
        });

        DB::table('spk_bahan')
            ->whereNull('tanggal_pemesanan')
            ->update([
                'tanggal_pemesanan' => DB::raw('COALESCE(DATE(created_at), tanggal_pembayaran)'),
            ]);

        DB::table('spk_bahan')
            ->whereRaw("LOWER(jenis_pembayaran) = 'tempo'")
            ->whereNull('tanggal_jatuh_tempo')
            ->update([
                'tanggal_jatuh_tempo' => DB::raw('tanggal_pembayaran'),
            ]);
    }

    public function down(): void
    {
        Schema::table('spk_bahan', function (Blueprint $table) {
            if (Schema::hasColumn('spk_bahan', 'tanggal_jatuh_tempo')) {
                $table->dropColumn('tanggal_jatuh_tempo');
            }

            if (Schema::hasColumn('spk_bahan', 'tanggal_pemesanan')) {
                $table->dropColumn('tanggal_pemesanan');
            }
        });
    }
};
