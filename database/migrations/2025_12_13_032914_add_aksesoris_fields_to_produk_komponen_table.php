<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAksesorisFieldsToProdukKomponenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('produk_komponen', function (Blueprint $table) {

            // sumber data komponen
            $table->string('sumber_komponen')
                  ->default('bahan')
                  ->after('jenis_komponen');

            // relasi ke aksesoris
            $table->foreignId('aksesoris_id')
                  ->nullable()
                  ->after('bahan_id')
                  ->constrained('aksesoris')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('produk_komponen', function (Blueprint $table) {
            $table->dropForeign(['aksesoris_id']);
            $table->dropColumn(['aksesoris_id', 'sumber_komponen']);
        });
    }
}
