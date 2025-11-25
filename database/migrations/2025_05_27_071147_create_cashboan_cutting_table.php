<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashboanCuttingTable extends Migration
{
    
    public function up()
    {
        Schema::create('cashboan_cutting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tukang_cutting_id')->constrained('tukang_cutting')->onDelete('cascade');
            $table->decimal('jumlah_cashboan', 12, 2);
            $table->enum('status_pembayaran', ['belum lunas', 'lunas'])->default('belum lunas');
            $table->date('tanggal_cashboan');
            $table->string('bukti_transfer')->nullable();
            $table->timestamps();
        });
    }

  
    public function down()
    {
        Schema::dropIfExists('cashboan_cutting');
    }
}
