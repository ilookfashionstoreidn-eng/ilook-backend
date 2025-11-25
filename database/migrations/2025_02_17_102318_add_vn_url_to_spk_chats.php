<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVnUrlToSpkChats extends Migration
{
    
    public function up()
    {
        Schema::table('spk_chats', function (Blueprint $table) {
            $table->string('vn_url')->nullable()->after('video_url');
        });
    }

  
    public function down()
{
    Schema::table('spk_chats', function (Blueprint $table) {
        $table->dropColumn('vn_url');
    });
}
}
