<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeIdSpkNullableOnPengirimanTable extends Migration
{
    public function up()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropForeign(['id_spk']);
            $table->unsignedBigInteger('id_spk')->nullable()->change();
            $table->foreign('id_spk')->references('id_spk')->on('spk_cmt')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropForeign(['id_spk']);
            $table->unsignedBigInteger('id_spk')->nullable(false)->change();
            $table->foreign('id_spk')->references('id_spk')->on('spk_cmt')->onDelete('cascade');
        });
    }
}
