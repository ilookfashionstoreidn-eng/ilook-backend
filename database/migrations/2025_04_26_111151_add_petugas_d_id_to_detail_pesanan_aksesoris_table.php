<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPetugasDIdToDetailPesananAksesorisTable extends Migration
{
    public function up()
    {
        Schema::table('detail_pesanan_aksesoris', function (Blueprint $table) {
            // Menambahkan kolom 'petugas_d_verif_id' sebagai foreign key yang merujuk ke tabel 'petugas_d_verif'
            $table->foreignId('petugas_d_verif_id')->nullable()->constrained('petugas_d_verif')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('detail_pesanan_aksesoris', function (Blueprint $table) {
            // Menghapus kolom 'petugas_d_verif_id' jika rollback dilakukan
            $table->dropForeign(['petugas_d_verif_id']);
            $table->dropColumn('petugas_d_verif_id');
        });
    }
}
