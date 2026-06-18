<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSpkSamplesFields1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_samples', function (Blueprint $table) {
            $table->dropColumn(['status_spk', 'status_proses', 'tahap_proses']);

            $table->string('bahan_utama')->nullable();
            $table->string('bahan_kombinasi')->nullable();
            $table->text('aksesoris')->nullable();
            $table->string('warna_yang_akan_dikeluarkan')->nullable();
            $table->integer('harga_potong')->nullable();
            $table->integer('harga_cmt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_samples', function (Blueprint $table) {
            $table->string('status_spk')->default('Draft');
            $table->string('status_proses')->nullable();
            $table->string('tahap_proses')->nullable();

            $table->dropColumn([
                'bahan_utama',
                'bahan_kombinasi',
                'aksesoris',
                'warna_yang_akan_dikeluarkan',
                'harga_potong',
                'harga_cmt'
            ]);
        });
    }
}
