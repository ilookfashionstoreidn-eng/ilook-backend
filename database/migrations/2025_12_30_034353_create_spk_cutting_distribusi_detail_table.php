<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCuttingDistribusiDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spk_cutting_distribusi_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spk_cutting_distribusi_id')
                ->constrained('spk_cutting_distribusi')
                ->cascadeOnDelete();

            $table->string('warna', 50);
            $table->integer('jumlah_produk');

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
        Schema::dropIfExists('spk_cutting_distribusi_detail');
    }
}
