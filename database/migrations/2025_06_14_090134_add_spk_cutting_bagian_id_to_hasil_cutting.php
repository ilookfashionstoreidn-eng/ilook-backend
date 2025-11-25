<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSpkCuttingBagianIdToHasilCutting extends Migration
{
   
    public function up()
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
        $table->foreignId('spk_cutting_bagian_id')
              ->nullable()
              ->constrained('spk_cutting_bagian')
              ->onDelete('set null');
        $table->integer('total_hasil_pendapatan')->nullable();
    });
    }

    
    public function down()
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->dropForeign(['spk_cutting_bagian_id']);
            $table->dropColumn(['spk_cutting_bagian_id', 'total_hasil_pendapatan']);
        });
    }
}
