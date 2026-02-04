<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexStatusPengambilanToSpkJasaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->index('status_pengambilan');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->dropIndex(['status_pengambilan']);
        });
    }
}
