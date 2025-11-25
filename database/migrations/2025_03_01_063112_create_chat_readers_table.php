<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatReadersTable extends Migration
{
    public function up()
    {
        Schema::create('chat_readers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('spk_chats')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_readers');
    }
}
