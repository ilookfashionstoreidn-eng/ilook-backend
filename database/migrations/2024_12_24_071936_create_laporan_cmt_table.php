<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLaporanCmtTable extends Migration
{
    
    public function up()
    { Schema::create('laporan_cmt', function (Blueprint $table) {
        $table->id('id_laporan');
        $table->unsignedBigInteger('id_spk'); // Relasi ke tabel spk_cmt
            $table->date('tgl_pengiriman'); // Tanggal pengiriman barang
            $table->integer('jumlah_dikirim'); // Jumlah barang yang dikirimkan
            $table->integer('barang_rusak')->default(0); // Jumlah barang rusak (default 0)
            $table->integer('barang_hilang')->default(0); // Jumlah barang hilang (default 0)
            $table->decimal('upah_per_barang', 12, 2)->default(0.00); // Upah per unit barang
            $table->decimal('total_upah', 12, 2)->default(0.00); // Total upah yang dibayarkan
            $table->decimal('potongan', 12, 2)->default(0.00); // Potongan untuk barang rusak/hilang
            $table->decimal('cashbon', 12, 2)->default(0.00); // Cashbon (hutang penjahit)
            $table->enum('status_pembayaran', ['Paid', 'Unpaid'])->default('Unpaid'); // Status pembayaran
            $table->text('keterangan')->nullable(); // Catatan tambahan
            $table->timestamps(); // Created_at dan Updated_at

            // Foreign Key
            $table->foreign('id_spk')->references('id_spk')->on('spk_cmt')->onDelete('cascade');
        });
    }
   
    public function down()
    {
        Schema::dropIfExists('laporan_cmt');
    }
}
