<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSpkCmtIdToPetugasCTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('petugas_c', function (Blueprint $table) {
            $table->unsignedBigInteger('spk_cmt_id')->nullable()->after('user_id');
            // Assuming the SpkCmt primary key is id_spk
            $table->foreign('spk_cmt_id')->references('id_spk')->on('spk_cmt')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('petugas_c', function (Blueprint $table) {
            $table->dropForeign(['spk_cmt_id']);
            $table->dropColumn('spk_cmt_id');
        });
    }
}
