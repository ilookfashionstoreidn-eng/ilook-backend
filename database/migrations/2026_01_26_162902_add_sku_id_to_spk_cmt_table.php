<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSkuIdToSpkCmtTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->unsignedBigInteger('sku_id')
                ->nullable()
                ->after('id_spk');

            $table->foreign('sku_id')
                ->references('id')
                ->on('skus')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropForeign(['sku_id']);
            $table->dropColumn('sku_id');
        });
    }
}
