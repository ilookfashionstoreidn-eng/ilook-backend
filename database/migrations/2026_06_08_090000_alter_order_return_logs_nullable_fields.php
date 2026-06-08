<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterOrderReturnLogsNullableFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_return_logs', function (Blueprint $table) {
            // Hapus foreign key constraint dulu sebelum ubah kolom
            $table->dropForeign(['order_id']);

            // order_id nullable (return tanpa resi tidak memiliki order)
            $table->unsignedBigInteger('order_id')->nullable()->change();

            // tracking_number nullable
            $table->string('tracking_number')->nullable()->change();

            // Tambah kembali foreign key constraint dengan nullable
            $table->foreign('order_id')->references('id')->on('order')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_return_logs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);

            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->string('tracking_number')->nullable(false)->change();

            $table->foreign('order_id')->references('id')->on('order')->onDelete('cascade');
        });
    }
}
