<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            // Tambah kolom jumlah_asumsi_produk (boleh null)
            if (!Schema::hasColumn('spk_cutting', 'jumlah_asumsi_produk')) {
                $table->unsignedInteger('jumlah_asumsi_produk')->nullable()->after('satuan_harga');
            }

            // Tambah kolom jenis_spk (boleh null), opsi: Terjual, Fittingan, Habisin Bahan
            if (!Schema::hasColumn('spk_cutting', 'jenis_spk')) {
                $table->enum('jenis_spk', ['Terjual', 'Fittingan', 'Habisin Bahan'])->nullable()->after('jumlah_asumsi_produk');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            if (Schema::hasColumn('spk_cutting', 'jenis_spk')) {
                $table->dropColumn('jenis_spk');
            }

            if (Schema::hasColumn('spk_cutting', 'jumlah_asumsi_produk')) {
                $table->dropColumn('jumlah_asumsi_produk');
            }
        });
    }
};
