<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKeteranganToLogStatusSpkCmtTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        Schema::table('log_status_spk_cmt', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('log_status_spk_cmt', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
}
