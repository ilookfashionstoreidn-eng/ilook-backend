<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTanggalJatuhTempoFromCashboanTable extends Migration
{
    public function up()
    {
        Schema::table('cashboan', function (Blueprint $table) {
            $table->dropColumn('tanggal_jatuh_tempo');
        });
    }

    public function down()
    {
        Schema::table('cashboan', function (Blueprint $table) {
            $table->date('tanggal_jatuh_tempo')->nullable()->after('status_pembayaran');
        });
    }
}
