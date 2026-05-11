<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_bahan', function (Blueprint $table) {
            if (!Schema::hasColumn('spk_bahan', 'estimasi_pengiriman')) {
                $table->date('estimasi_pengiriman')->nullable()->after('tanggal_jatuh_tempo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spk_bahan', function (Blueprint $table) {
            if (Schema::hasColumn('spk_bahan', 'estimasi_pengiriman')) {
                $table->dropColumn('estimasi_pengiriman');
            }
        });
    }
};
