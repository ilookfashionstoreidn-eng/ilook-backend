<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFotoNotaAndStatusVerifikasiToPengirimanTable extends Migration
{
    public function up()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->string('foto_nota')->nullable();
            $table->string('status_verifikasi')->nullable();

        });
    }

    public function down()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropColumn(['foto_nota', 'status_verifikasi']);
        });
    }
}
