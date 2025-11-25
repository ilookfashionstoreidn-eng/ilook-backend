<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKontakBankNorekToTukangCuttingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tukang_cutting', function (Blueprint $table) {
           $table->string('kontak')->nullable();
           $table->string('bank')->nullable();
           $table->string('no_rekening')->nullable();
           $table->string('alamat', 255)->nullable();
          
        });
    }

    
    public function down()
    {
        Schema::table('tukang_cutting', function (Blueprint $table) {
              $table->dropColumn(['kontak', 'bank', 'no_rekening', 'alamat']);
        });
    }
}
