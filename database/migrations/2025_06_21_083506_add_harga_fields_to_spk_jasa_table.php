<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHargaFieldsToSpkJasaTable extends Migration
{
   
    public function up()
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->decimal('harga', 12, 2)->nullable(); 
            $table->enum('opsi_harga', ['pcs', 'lusin'])->nullable();
            $table->decimal('harga_per_pcs', 10, 2)->nullable();
        });
    }

   
    public function down()
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->dropColumn(['harga', 'opsi_harga', 'harga_per_pcs']);
        });
    }
}
