<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHargaBarangDasarToSpkCmtTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up(): void
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->decimal('harga_barang_dasar', 15, 2)
                ->after('merek');

            $table->enum('jenis_harga_barang', ['per_pcs', 'per_lusin'])
                ->after('harga_barang_dasar');
        });
    }

    public function down(): void
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropColumn([
                'harga_barang_dasar',
                'jenis_harga_barang',
            ]);
        });
    }
}
