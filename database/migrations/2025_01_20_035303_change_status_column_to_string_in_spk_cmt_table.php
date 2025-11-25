<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeStatusColumnToStringInSpkCmtTable extends Migration
{
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            // Hapus kolom status lama
            $table->dropColumn('status');
        });

        Schema::table('spk_cmt', function (Blueprint $table) {
            // Tambahkan kolom status baru dengan tipe string
            $table->string('status', 50)->default('Pending');
        });
    }

    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            // Hapus kolom status string
            $table->dropColumn('status');
        });

        Schema::table('spk_cmt', function (Blueprint $table) {
            // Tambahkan kolom status lama dengan enum
            $table->enum('status', ['Pending', 'In Progress', 'Completed'])->default('Pending');
        });
    }
}
