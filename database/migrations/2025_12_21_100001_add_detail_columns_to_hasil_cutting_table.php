<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDetailColumnsToHasilCuttingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Hapus kolom detail dari tabel hasil_cutting_bahan jika ada (dari migration sebelumnya yang salah)
        if (Schema::hasColumn('hasil_cutting_bahan', 'nama_bagian')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->dropColumn('nama_bagian');
            });
        }

        if (Schema::hasColumn('hasil_cutting_bahan', 'nama_bahan')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->dropColumn('nama_bahan');
            });
        }

        if (Schema::hasColumn('hasil_cutting_bahan', 'warna')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->dropColumn('warna');
            });
        }

        if (Schema::hasColumn('hasil_cutting_bahan', 'qty')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->dropColumn('qty');
            });
        }

        // Tambah kolom detail ke tabel hasil_cutting
        if (!Schema::hasColumn('hasil_cutting', 'nama_bagian')) {
            Schema::table('hasil_cutting', function (Blueprint $table) {
                $table->string('nama_bagian')->nullable()->after('spk_cutting_bagian_id');
            });
        }

        if (!Schema::hasColumn('hasil_cutting', 'nama_bahan')) {
            Schema::table('hasil_cutting', function (Blueprint $table) {
                $table->string('nama_bahan')->nullable()->after('nama_bagian');
            });
        }

        if (!Schema::hasColumn('hasil_cutting', 'warna')) {
            Schema::table('hasil_cutting', function (Blueprint $table) {
                $table->string('warna')->nullable()->after('nama_bahan');
            });
        }

        if (!Schema::hasColumn('hasil_cutting', 'qty')) {
            Schema::table('hasil_cutting', function (Blueprint $table) {
                $table->integer('qty')->nullable()->after('warna');
            });
        }

        if (!Schema::hasColumn('hasil_cutting', 'total_produk')) {
            Schema::table('hasil_cutting', function (Blueprint $table) {
                $table->integer('total_produk')->nullable()->after('data_acuan');
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
        // Hapus kolom dari hasil_cutting
        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->dropColumn(['nama_bagian', 'nama_bahan', 'warna', 'qty', 'total_produk']);
        });

        // Kembalikan kolom ke hasil_cutting_bahan (jika diperlukan)
        if (!Schema::hasColumn('hasil_cutting_bahan', 'nama_bagian')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->string('nama_bagian')->nullable()->after('spk_cutting_bagian_id');
            });
        }

        if (!Schema::hasColumn('hasil_cutting_bahan', 'nama_bahan')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->string('nama_bahan')->nullable()->after('nama_bagian');
            });
        }

        if (!Schema::hasColumn('hasil_cutting_bahan', 'warna')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->string('warna')->nullable()->after('nama_bahan');
            });
        }

        if (!Schema::hasColumn('hasil_cutting_bahan', 'qty')) {
            Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
                $table->integer('qty')->nullable()->after('warna');
            });
        }
    }
}
