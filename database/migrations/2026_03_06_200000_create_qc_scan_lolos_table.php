<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQcScanLolosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qc_scan_lolos', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_seri');
            $table->string('sku');
            $table->integer('jumlah')->default(1);
            $table->timestamps();

            // Index untuk query groupby nomor_seri & sku
            $table->index(['nomor_seri', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('qc_scan_lolos');
    }
}
