<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusPengambilanToSpkJasaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->enum('status_pengambilan', [
                'belum_diambil',
                'sudah_diambil',
                'batal_diambil',
                'selesai'
            ])->default('belum_diambil')->after('tanggal_ambil');
        });
    }

    public function down(): void
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->dropColumn('status_pengambilan');
        });
    }
}
