<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSpkCuttingBagianIdToHasilCuttingBahanTable extends Migration
{
   
    public function up()
    {
        Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
             $table->foreignId('spk_cutting_bagian_id')
              ->nullable()
              ->constrained('spk_cutting_bagian')
              ->onDelete('set null');
         });
        
    }

   
    public function down()
    {
        Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
             $table->dropForeign(['spk_cutting_bagian_id']);
             $table->dropColumn('spk_cutting_bagian_id');
        });
    }
}
