<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCmtItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::create('spk_cmt_items', function (Blueprint $table) {
            $table->id();

            // relasi ke SPK CMT (header)
            $table->unsignedBigInteger('spk_cmt_id');
            // relasi ke SKU
            $table->unsignedBigInteger('sku_id');

            $table->timestamps();

            // ===============================
            // FOREIGN KEY
            // ===============================
            $table->foreign('spk_cmt_id')
                ->references('id_spk')
                ->on('spk_cmt')
                ->onDelete('cascade');

            $table->foreign('sku_id')
                ->references('id')
                ->on('skus')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spk_cmt_items');
    }
}
