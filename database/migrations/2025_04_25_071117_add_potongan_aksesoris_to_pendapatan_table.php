<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPotonganAksesorisToPendapatanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pendapatan', function (Blueprint $table) {
            $table->integer('potongan_aksesoris')->default(0)->after('total_cashbon');
        });
    }

   
    public function down()
    {
        Schema::table('pendapatan', function (Blueprint $table) {
            //
        });
    }
}
