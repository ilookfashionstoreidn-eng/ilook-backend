<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVideoUrlToSpkChats extends Migration
{
    public function up()
    {
        Schema::table('spk_chats', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('image_url');
        });
    }

  
    public function down()
{
    Schema::table('spk_chats', function (Blueprint $table) {
        $table->dropColumn('video_url');
    });
}
}
