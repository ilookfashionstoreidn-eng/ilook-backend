<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoryCashboanTable extends Migration
{
    
    public function up()
     {
            Schema::create('history_cashboan', function (Blueprint $table) {
                $table->id('id_history');
                $table->unsignedBigInteger('id_cashboan');
                $table->decimal('jumlah_cashboan', 15, 2);
                $table->decimal('perubahan_cashboan', 15, 2);
                $table->enum('jenis_perubahan', ['penambahan', 'pengurangan']);
                $table->timestamp('tanggal_perubahan')->useCurrent();
                $table->timestamps();

            // Foreign key ke hutang
            $table->foreign('id_cashboan')->references('id_cashboan')->on('cashboan')->onDelete('cascade');
    
            });
    }
        

    public function down()
    {
        Schema::table('history_cashboan', function (Blueprint $table) {
            Schema::dropIfExists('history_cashboan');
        });
    }
}
