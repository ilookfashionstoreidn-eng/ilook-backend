<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHasilCuttingBahanTable extends Migration
{
    
    public function up()
    {
        Schema::create('hasil_cutting_bahan', function (Blueprint $table) {
            $table->id();
             $table->foreignId('hasil_cutting_id')
            ->constrained('hasil_cutting') 
            ->onDelete('cascade');

            $table->foreignId('spk_cutting_bahan_id')
                ->constrained('spk_cutting_bahan')
                ->onDelete('cascade');
                
            $table->float('berat')->nullable(); // hasil aktual berat
            $table->integer('hasil')->nullable(); // hasil aktual jumlah
            $table->timestamps();
        });
    }

   
    public function down()
    {
        Schema::dropIfExists('hasil_cutting_bahan');
    }
}
