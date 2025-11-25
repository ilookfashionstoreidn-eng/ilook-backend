<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNomorSeriToSpkTable extends Migration
{
    
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->string('nomor_seri')->nullable();
        });
    }

    
    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropColumn('nomor_seri');
        });
    }
}
