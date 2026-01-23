<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusBayarToPembelianBahanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table
                ->enum('status_bayar', ['belum', 'sudah'])
                ->default('belum')
                ->after('harga');
        });
    }

    public function down(): void
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table->dropColumn('status_bayar');
        });
    }
}
