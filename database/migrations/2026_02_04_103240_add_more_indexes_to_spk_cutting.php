<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreIndexesToSpkCutting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            // Index untuk produk_id (sering digunakan untuk filtering dan sorting)
            $table->index('produk_id', 'idx_spk_cutting_produk_id');
            
            // Index untuk tukang_pola_id (untuk relasi dan optimasi lookup)
            $table->index('tukang_pola_id', 'idx_spk_cutting_tukang_pola_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            $table->dropIndex('idx_spk_cutting_produk_id');
            $table->dropIndex('idx_spk_cutting_tukang_pola_id');
        });
    }
}
