<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePendapatanPabrikTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pendapatan_pabrik', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pabrik_id')
                ->constrained('pabrik')
                ->cascadeOnDelete();

            $table->date('tanggal_bayar');
            $table->decimal('total_bayar', 15, 2);

            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pendapatan_pabrik');
    }
}
