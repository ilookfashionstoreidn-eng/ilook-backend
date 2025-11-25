<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusProdukToProdukTable extends Migration
{
    
    public function up()
    {
        Schema::table('produk', function (Blueprint $table) {
              $table->string('status_produk')->default('sementara')->after('hpp');
        });
    }

  
    public function down()
    {
        Schema::table('produk', function (Blueprint $table) {
             $table->dropColumn('status_produk');
        });
    }
}
