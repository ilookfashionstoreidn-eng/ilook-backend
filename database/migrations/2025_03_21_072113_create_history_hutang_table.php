<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoryHutangTable extends Migration
{
   
    public function up()
    {
        Schema::create('history_hutang', function (Blueprint $table) {
            $table->id('id_history');
            $table->unsignedBigInteger('id_hutang');
            $table->decimal('jumlah_hutang', 15, 2); // Hutang setelah perubahan
            $table->decimal('perubahan_hutang', 15, 2); // Jumlah hutang yang ditambahkan atau dikurangi
            $table->enum('jenis_perubahan', ['penambahan', 'pengurangan']); // Tipe perubahan
            $table->timestamp('tanggal_perubahan')->useCurrent();
            $table->timestamps();
        
            // Foreign key ke hutang
            $table->foreign('id_hutang')->references('id_hutang')->on('hutang')->onDelete('cascade');
        });
        
    }

  
   public function down()
    {
        Schema::dropIfExists('history_hutang');
    }
}
