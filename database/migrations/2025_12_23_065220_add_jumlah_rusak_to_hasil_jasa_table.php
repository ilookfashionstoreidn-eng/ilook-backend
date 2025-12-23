<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJumlahRusakToHasilJasaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up(): void
    {
        Schema::table('hasil_jasa', function (Blueprint $table) {
            $table->integer('jumlah_rusak')->default(0)->after('jumlah_hasil');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_jasa', function (Blueprint $table) {
            $table->dropColumn('jumlah_rusak');
        });
    }
}
