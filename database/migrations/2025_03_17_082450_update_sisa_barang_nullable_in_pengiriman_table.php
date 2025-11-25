<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSisaBarangNullableInPengirimanTable extends Migration
{
    public function up()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->integer('sisa_barang')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->integer('sisa_barang')->nullable(false)->change(); // Balik ke awal jika rollback
        });
    }
}
