<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoryCashboanCuttingTable extends Migration
{
    
    public function up()
    {
        Schema::create('history_cashboan_cutting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashboan_cutting_id')->constrained('cashboan_cutting')->onDelete('cascade');
            $table->decimal('jumlah_cashboan', 12, 2);
            $table->decimal('perubahan_cashboan', 12, 2)->nullable();
            $table->enum('jenis_perubahan', ['penambahan', 'pengurangan']);
            $table->string('bukti_transfer')->nullable();
            $table->timestamp('tanggal_perubahan')->useCurrent();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('history_cashboan_cutting');
    }
}
