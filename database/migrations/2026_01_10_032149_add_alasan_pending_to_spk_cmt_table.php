<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAlasanPendingToSpkCmtTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->text('alasan_pending')->nullable()->after('pending_until');
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
            $table->dropColumn('alasan_pending');
        });
    }
}
