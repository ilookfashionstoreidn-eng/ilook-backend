<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBiayaToProdukTable extends Migration
{
   
    public function up()
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->decimal('harga_jasa_cutting', 15, 2)->default(0);
            $table->decimal('harga_jasa_cmt', 15, 2)->default(0);
            $table->decimal('harga_jasa_aksesoris', 15, 2)->default(0);
            $table->decimal('harga_overhead', 15, 2)->default(0);
            $table->decimal('hpp', 15, 2)->default(0);
        });
    }

    public function down()
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn([
                'harga_jasa_cutting',
                'harga_jasa_cmt',
                'harga_jasa_aksesoris',
                'harga_overhead',
                'hpp',
            ]);
        });
    }
}
