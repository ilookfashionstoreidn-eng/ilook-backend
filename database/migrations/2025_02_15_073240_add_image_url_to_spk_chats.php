<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageUrlToSpkChats extends Migration
{
    public function up()
{
    Schema::table('spk_chats', function (Blueprint $table) {
        $table->string('image_url')->nullable()->after('message'); // Tambah kolom image_url
    });
}

public function down()
{
    Schema::table('spk_chats', function (Blueprint $table) {
        $table->dropColumn('image_url');
    });
}

}
