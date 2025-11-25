<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePetugasDVerifTable extends Migration
{
    
    public function up()
    {
        Schema::create('petugas_d_verif', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('petugas_c_id')->constrained('petugas_c')->onDelete('cascade');
            $table->string('barcode');
            $table->timestamps();            
        });
    }

   
    public function down()
    {
        Schema::dropIfExists('petugas_d_verif');
    }
}
