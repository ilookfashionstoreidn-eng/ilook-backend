<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBuktiTransferToHistoryHutangTable extends Migration
{
    
    public function up()
    {
        Schema::table('history_hutang', function (Blueprint $table) {
            $table->string('bukti_transfer')->nullable();
        });
    }

    
    public function down()
    {
        Schema::table('history_hutang', function (Blueprint $table) {
            $table->dropColumn('bukti_transfer');
        });
    }
}
