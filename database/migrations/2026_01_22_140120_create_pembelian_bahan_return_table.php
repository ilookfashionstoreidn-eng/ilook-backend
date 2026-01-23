<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePembelianBahanReturnTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pembelian_bahan_return', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_bahan_id')->constrained('pembelian_bahan')->onDelete('cascade');
            $table->foreignId('pembelian_bahan_rol_id')->nullable()->constrained('pembelian_bahan_rol')->onDelete('set null');
            
            // Informasi return
            $table->enum('tipe_return', ['refund', 'return_barang'])->comment('refund = pengembalian uang, return_barang = pengembalian barang');
            $table->integer('jumlah_rol')->default(1)->comment('Jumlah rol yang dikembalikan');
            $table->decimal('total_refund', 15, 2)->nullable()->comment('Total uang yang direfund (jika tipe_return = refund)');
            $table->text('keterangan')->nullable()->comment('Alasan return/refund');
            $table->date('tanggal_return')->comment('Tanggal return/refund dilakukan');
            $table->string('status')->default('pending')->comment('pending, approved, rejected, completed');
            
            // Informasi tambahan
            $table->string('foto_bukti')->nullable()->comment('Foto bukti barang rusak');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pembelian_bahan_return');
    }
}
