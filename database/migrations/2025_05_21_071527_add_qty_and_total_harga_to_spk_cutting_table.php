<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQtyAndTotalHargaToSpkCuttingTable extends Migration
{
    public function up()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
          $table->integer('jumlah_qty_potong');
         $table->decimal('total_harga_jasa', 15, 2);
        });
    }

    
    public function down()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
             $table->dropColumn(['jumlah_qty_potong', 'total_harga_jasa']);
        });
    }
}
