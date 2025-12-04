<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeNamaBahanToBahanIdInSpkCuttingBahanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Cek apakah kolom bahan_id sudah ada
        if (!Schema::hasColumn('spk_cutting_bahan', 'bahan_id')) {
            Schema::table('spk_cutting_bahan', function (Blueprint $table) {
                // Tambahkan kolom bahan_id dulu (nullable)
                $table->unsignedBigInteger('bahan_id')->nullable()->after('spk_cutting_bagian_id');
            });
        }

        // Migrate data: cari bahan berdasarkan nama_bahan dan set bahan_id (jika kolom nama_bahan masih ada)
        if (Schema::hasColumn('spk_cutting_bahan', 'nama_bahan')) {
            DB::statement("UPDATE spk_cutting_bahan scb INNER JOIN bahan b ON b.nama_bahan = scb.nama_bahan SET scb.bahan_id = b.id WHERE scb.bahan_id IS NULL");
        }

        // Set bahan_id menjadi not null dan tambahkan foreign key (jika belum ada)
        if (Schema::hasColumn('spk_cutting_bahan', 'bahan_id')) {
            try {
                Schema::table('spk_cutting_bahan', function (Blueprint $table) {
                    $table->unsignedBigInteger('bahan_id')->nullable(false)->change();
                    $table->foreign('bahan_id')->references('id')->on('bahan')->onDelete('restrict');
                });
            } catch (\Exception $e) {
                // Foreign key mungkin sudah ada, skip
            }
        }

        // Hapus kolom nama_bahan jika masih ada
        if (Schema::hasColumn('spk_cutting_bahan', 'nama_bahan')) {
            Schema::table('spk_cutting_bahan', function (Blueprint $table) {
                $table->dropColumn('nama_bahan');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_cutting_bahan', function (Blueprint $table) {
            // Kembalikan kolom nama_bahan dan hapus bahan_id
            $table->dropForeign(['bahan_id']);
            $table->dropColumn('bahan_id');
            $table->string('nama_bahan')->after('spk_cutting_bagian_id');
        });
    }
}
