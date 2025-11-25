<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveNomorSeriFromHasilCuttingTable extends Migration
{
    
    public function up()
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->dropColumn('nomor_seri');
        });
    }

  
    public function down()
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
             $table->string('nomor_seri')->nullable();
        });
    }
}
