<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHasilMarkeranTable extends Migration
{
   
    public function up()
    {
        Schema::create('hasil_markeran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hasil_cutting_id')->constrained('hasil_cutting')->onDelete('cascade');
            $table->string('nama_komponen');
            $table->decimal('total_panjang', 8, 2); 
            $table->integer('jumlah_hasil'); 
            $table->decimal('berat_per_pcs', 8, 3); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hasil_markeran');
    }
}
