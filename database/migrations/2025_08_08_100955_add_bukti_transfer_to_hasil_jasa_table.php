<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBuktiTransferToHasilJasaTable extends Migration
{
    
    public function up()
    {
        Schema::table('hasil_jasa', function (Blueprint $table) {
              $table->string('bukti_transfer')->nullable(); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hasil_jasa', function (Blueprint $table) {
            $table->dropColumn('bukti_transfer');
        });
    }
}
