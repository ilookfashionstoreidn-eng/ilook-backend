<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHasilCuttingTable extends Migration
{
   
    public function up()
    {
        Schema::create('hasil_cutting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_cutting_id')
            ->nullable() // dibutuhkan supaya bisa "set null" saat SPK-nya dihapus
            ->constrained('spk_cutting')
            ->onDelete('set null');
            $table->string('nomor_seri')->unique();
            $table->string('foto_komponen')->nullable(); 
            $table->integer('jumlah_komponen')->default(0);
            $table->timestamps();
        });
    }

   
    public function down()
    {
        Schema::dropIfExists('hasil_cutting');
    }
}
