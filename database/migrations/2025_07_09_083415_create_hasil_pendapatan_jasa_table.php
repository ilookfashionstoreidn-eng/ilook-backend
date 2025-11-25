<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHasilPendapatanJasaTable extends Migration
{
    
    public function up()
    {
        Schema::create('hasil_pendapatan_jasa', function (Blueprint $table) {
           $table->id();
           $table->foreignId('pendapatan_jasa_id')->constrained('pendapatan_jasa')->onDelete('cascade');
           $table->foreignId('hasil_jasa_id')->constrained('hasil_jasa')->onDelete('cascade');
           $table->timestamps();
        });
    }

    
    public function down()
    {
        Schema::dropIfExists('hasil_pendapatan_jasa');
    }
}
