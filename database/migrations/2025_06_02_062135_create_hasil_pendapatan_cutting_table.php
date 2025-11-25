<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHasilPendapatanCuttingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hasil_pendapatan_cutting', function (Blueprint $table) {
           $table->id();
           $table->foreignId('pendapatan_cutting_id')->constrained('pendapatan_cutting')->onDelete('cascade');
           $table->foreignId('hasil_cutting_id')->constrained('hasil_cutting')->onDelete('cascade');
           $table->timestamps();
        });
    }


    public function down()
    {
        Schema::dropIfExists('hasil_pendapatan_cutting');
    }
}
