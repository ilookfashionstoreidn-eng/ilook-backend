<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveIdSpkFromCashboanTable extends Migration
{
    
    public function up()
    {
        Schema::table('cashboan', function (Blueprint $table) {
           $table->dropForeign(['id_spk']);
           $table->dropColumn('id_spk');
        });
    }


    public function down()
    {
        Schema::table('cashboan', function (Blueprint $table) {
          $table->unsignedBigInteger('id_spk')->nullable();
          $table->foreign('id_spk')->references('id_spk')->on('spk_cmt')->onDelete('cascade');
        });
    }
}
