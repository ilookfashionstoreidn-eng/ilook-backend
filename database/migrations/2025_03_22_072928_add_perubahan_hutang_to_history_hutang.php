<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerubahanHutangToHistoryHutang extends Migration
{
    public function up()
    {
        Schema::table('history_hutang', function (Blueprint $table) {
            $table->decimal('perubahan_hutang', 15, 2)->nullable()->after('jumlah_hutang');
        });
    }

    public function down()
    {
        Schema::table('history_hutang', function (Blueprint $table) {
            $table->dropColumn('perubahan_hutang');
        });
    }
}
