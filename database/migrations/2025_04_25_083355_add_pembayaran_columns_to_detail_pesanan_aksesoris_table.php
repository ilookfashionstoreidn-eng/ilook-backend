<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPembayaranColumnsToDetailPesananAksesorisTable extends Migration
{
    
    public function up()
    {
        Schema::table('detail_pesanan_aksesoris', function (Blueprint $table) {
            
            $table->boolean('sudah_dibayar')->default(false)->after('total_harga');
            $table->foreignId('id_pendapatan')
            ->nullable()
            ->constrained('pendapatan', 'id_pendapatan')
            ->onDelete('set null');      
        });
    }

   
    public function down()
    {
        Schema::table('detail_pesanan_aksesoris', function (Blueprint $table) {
            $table->dropForeign(['id_pendapatan']);
            $table->dropColumn(['sudah_dibayar', 'id_pendapatan']);
        });
    }
}
