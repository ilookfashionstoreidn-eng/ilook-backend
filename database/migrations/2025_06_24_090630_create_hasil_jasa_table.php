<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHasilJasaTable extends Migration
{
    public function up()
    {
        Schema::create('hasil_jasa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_jasa_id')->constrained('spk_jasa')->onDelete('cascade');
            $table->date('tanggal');
            $table->integer('jumlah_hasil')->default(0);
            $table->decimal('total_pendapatan', 12, 2)->default(0);
            $table->timestamps();
        });
    }

   
    public function down()
    {
        Schema::dropIfExists('hasil_jasa');
    }
}
