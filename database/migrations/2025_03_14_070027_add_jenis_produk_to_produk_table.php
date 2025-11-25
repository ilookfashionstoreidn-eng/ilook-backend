<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJenisProdukToProdukTable extends Migration
{
    
    public function up()
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->string('jenis_produk')->nullable();

        });
    }

    
    public function down()
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn('jenis_produk');
        });
    }
}
