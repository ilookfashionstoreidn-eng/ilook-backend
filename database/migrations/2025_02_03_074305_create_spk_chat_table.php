<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkChatTable extends Migration
{
    public function up()
    {
        Schema::create('spk_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_spk')->constrained('spk_cmt', 'id_spk')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Hubungkan ke user
            $table->text('message'); // Isi pesan
            $table->timestamps(); // Waktu pesan dibuat
        });
    }

    public function down()
    {
        Schema::dropIfExists('spk_chats');
    }
}
