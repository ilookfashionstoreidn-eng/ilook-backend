<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNomorSeriToSpkCmtTableMigrasi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->string('nomor_seri')->nullable()->after('source_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropColumn('nomor_seri');
        });
    }
}
