<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLamaPemesananToSpkBahanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_bahan', function (Blueprint $table) {
            $table->integer('lama_pemesanan')->nullable()->after('status')->comment('Selisih hari dari SPK dibuat sampai Pembelian Bahan dibuat');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_bahan', function (Blueprint $table) {
            $table->dropColumn('lama_pemesanan');
        });
    }
}
