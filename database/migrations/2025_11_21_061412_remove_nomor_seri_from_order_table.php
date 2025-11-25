<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveNomorSeriFromOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn('nomor_seri');
        });
    }

    public function down()
    {
        Schema::table('order', function (Blueprint $table) {
            $table->string('nomor_seri')->nullable();
        });
    }

}
