<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHargaSatuanToAksesorisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('aksesoris', function (Blueprint $table) {
            $table->decimal('harga_jual', 15, 2)->nullable()->after('satuan');
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
            $table->dropColumn('harga_jual');
        });
    }
}
