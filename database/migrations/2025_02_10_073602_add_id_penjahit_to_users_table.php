<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdPenjahitToUsersTable extends Migration
{
    
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id_penjahit')->nullable()->after('id'); // nullable()
            $table->foreign('id_penjahit')->references('id_penjahit')->on('penjahit_cmt')->onDelete('set null');
        });
    }
    
    
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_penjahit']); // Hapus foreign key
            $table->dropColumn('id_penjahit'); // Hapus kolom
        });
    }
    
}
