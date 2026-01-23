<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalHargaToPembelianBahanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
              $table->decimal('total_harga', 15, 2)
                  ->default(0)
                  ->after('harga');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
             $table->dropColumn('total_harga');
        });
    }
}
