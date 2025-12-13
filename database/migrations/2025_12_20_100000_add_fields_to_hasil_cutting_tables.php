<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToHasilCuttingTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Tambah kolom ke tabel hasil_cutting_bahan
        if (!Schema::hasColumn('hasil_cutting_bahan', 'jumlah_lembar')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->integer('jumlah_lembar')->nullable()->after('spk_cutting_bagian_id');
            });
        }

        if (!Schema::hasColumn('hasil_cutting_bahan', 'jumlah_produk')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->integer('jumlah_produk')->nullable()->after('jumlah_lembar');
            });
        }

        if (!Schema::hasColumn('hasil_cutting_bahan', 'berat_per_produk')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->decimal('berat_per_produk', 10, 2)->nullable()->after('berat');
            });
        }

        // Tambah kolom data_acuan ke tabel hasil_cutting jika belum ada
        if (!Schema::hasColumn('hasil_cutting', 'data_acuan')) {
            Schema::table('hasil_cutting', function (Blueprint $table) {
                $table->json('data_acuan')->nullable()->after('total_hasil_pendapatan');
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
        Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
            $table->dropColumn(['jumlah_lembar', 'jumlah_produk', 'berat_per_produk']);
        });

        if (Schema::hasColumn('hasil_cutting', 'data_acuan')) {
            Schema::table('hasil_cutting', function (Blueprint $table) {
                $table->dropColumn('data_acuan');
            });
        }
    }
}
