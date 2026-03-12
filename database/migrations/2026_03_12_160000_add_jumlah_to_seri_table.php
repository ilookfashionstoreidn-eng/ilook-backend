<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJumlahToSeriTable extends Migration
{
    public function up()
    {
        Schema::table('seri', function (Blueprint $table) {
            $table->unsignedInteger('jumlah')->default(1)->after('sku');
        });
    }

    public function down()
    {
        Schema::table('seri', function (Blueprint $table) {
            $table->dropColumn('jumlah');
        });
    }
}
