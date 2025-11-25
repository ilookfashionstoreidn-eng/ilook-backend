<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalHargaToPetugasCTable extends Migration
{
   
    public function up()
    {
        Schema::table('petugas_c', function (Blueprint $table) {
            $table->decimal('total_harga', 15, 2)->default(0)->after('status');
        });
    }

    public function down()
    {
        Schema::table('petugas_c', function (Blueprint $table) {
            $table->dropColumn('total_harga');
        });
    }
}
