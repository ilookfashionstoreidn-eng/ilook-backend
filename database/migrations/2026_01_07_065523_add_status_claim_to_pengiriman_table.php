<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusClaimToPengirimanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->enum('status_claim', ['belum_dibayar', 'sudah_dibayar'])->default('belum_dibayar')->after('refund_claim');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropColumn('status_claim');
        });
    }
}
