<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFotoAksesorisToAksesorisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('aksesoris', function (Blueprint $table) {
            $table->string('foto_aksesoris')->nullable()->after('harga_jual');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('aksesoris', function (Blueprint $table) {
            $table->dropColumn('foto_aksesoris');
        });
    }
}
