<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCuttingBahanTable extends Migration
{
    
    public function up()
    {
        Schema::create('spk_cutting_bahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_cutting_bagian_id')->constrained('spk_cutting_bagian')->onDelete('cascade');
            $table->string('nama_bahan');
            $table->integer('qty');
            $table->timestamps();
        });
    }

    
    public function down()
    {
        Schema::dropIfExists('spk_cutting_bahan');
    }
}
