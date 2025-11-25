<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHutangCuttingTable extends Migration
{
    
    public function up()
    {
        Schema::create('hutang_cutting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tukang_cutting_id')->constrained('tukang_cutting')->onDelete('cascade');
            $table->decimal('jumlah_hutang', 12, 2);
            $table->string('status_pembayaran')->default('belum'); 
            $table->date('tanggal_hutang');
            $table->decimal('potongan_per_minggu', 12, 2)->nullable();
            $table->boolean('is_potongan_persen')->default(false);
            $table->decimal('persentase_potongan', 5, 2)->nullable(); 
            $table->string('bukti_transfer')->nullable(); 
            $table->timestamps();
        });
    }

    
    public function down()
    {
        Schema::dropIfExists('hutang_cutting');
    }
}
