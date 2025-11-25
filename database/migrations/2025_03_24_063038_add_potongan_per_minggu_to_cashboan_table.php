<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPotonganPerMingguToCashboanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cashboan', function (Blueprint $table) {
            $table->decimal('potongan_per_minggu', 15, 2)->nullable();
        });
    }

    public function down()
    {
        Schema::table('cashboan', function (Blueprint $table) {
            $table->dropColumn('potongan_per_minggu');

        });
    }
}
