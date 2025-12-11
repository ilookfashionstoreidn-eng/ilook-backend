<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHargaPerBijiToAksesorisTable extends Migration
{
    
    public function up()
    {
        Schema::table('aksesoris', function (Blueprint $table) {
            $table->integer('jumlah_per_satuan')->nullable()->after('satuan'); 
            $table->decimal('harga_per_biji', 15, 2)->nullable()->after('harga_jual');
        });
    }

    public function down()
    {
        Schema::table('aksesoris', function (Blueprint $table) {
            $table->dropColumn(['jumlah_per_satuan', 'harga_per_biji']);
        });
    }
}
