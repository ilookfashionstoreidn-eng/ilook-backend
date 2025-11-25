<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoryHutangCuttingTable extends Migration
{
    
    public function up()
    {
        Schema::create('history_hutang_cutting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hutang_cutting_id')->constrained('hutang_cutting')->onDelete('cascade');
            $table->decimal('jumlah_hutang', 12, 2);
            $table->decimal('perubahan_hutang', 12, 2);
            $table->enum('jenis_perubahan', ['penambahan', 'pengurangan']);
            $table->string('bukti_transfer')->nullable();
            $table->timestamp('tanggal_perubahan')->useCurrent();

        });
    }

    
    public function down()
    {
        Schema::dropIfExists('history_hutang_cutting');
    }
}
