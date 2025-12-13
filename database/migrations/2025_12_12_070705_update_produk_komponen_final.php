<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProdukKomponenFinal extends Migration
{
    public function up(): void
    {
        Schema::table('produk_komponen', function (Blueprint $table) {

            // Tambah bahan_id kalau belum ada
            if (!Schema::hasColumn('produk_komponen', 'bahan_id')) {
                $table->unsignedBigInteger('bahan_id')->after('jenis_komponen')->nullable();
                $table->foreign('bahan_id')->references('id')->on('bahan')->onDelete('cascade');
            }

            // Hapus kolom lama yang tidak dipakai
            if (Schema::hasColumn('produk_komponen', 'nama_bahan')) {
                $table->dropColumn('nama_bahan');
            }

            if (Schema::hasColumn('produk_komponen', 'satuan_bahan')) {
                $table->dropColumn('satuan_bahan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produk_komponen', function (Blueprint $table) {

            // Rollback bahan_id
            if (Schema::hasColumn('produk_komponen', 'bahan_id')) {
                $table->dropForeign(['bahan_id']);
                $table->dropColumn('bahan_id');
            }

            // Kembalikan kolom lama
            if (!Schema::hasColumn('produk_komponen', 'nama_bahan')) {
                $table->string('nama_bahan')->nullable();
            }

            if (!Schema::hasColumn('produk_komponen', 'satuan_bahan')) {
                $table->string('satuan_bahan')->nullable();
            }
        });
    }
}
