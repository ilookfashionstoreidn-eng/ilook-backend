<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTanggalAktivitasToLogStatusTable extends Migration
{
    
    public function up()
    {
        Schema::table('log_status', function (Blueprint $table) {
            $table->timestamp('tanggal_aktivitas')->nullable(); // Menambahkan kolom
        });
    }

    public function down()
    {
        Schema::table('log_status', function (Blueprint $table) {
            $table->dropColumn('tanggal_aktivitas'); // Menghapus kolom
        });
    }
}
