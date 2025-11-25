<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyBarcodeColumnInPetugasDVerifTable extends Migration
{
    public function up()
{
    Schema::table('petugas_d_verif', function (Blueprint $table) {
        $table->json('barcode')->change();
    });
}

public function down()
{
    Schema::table('petugas_d_verif', function (Blueprint $table) {
        $table->string('barcode', 255)->change();
    });
}

}
