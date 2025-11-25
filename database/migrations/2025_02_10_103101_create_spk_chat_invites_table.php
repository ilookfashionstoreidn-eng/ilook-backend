<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkChatInvitesTable extends Migration
{
    public function up()
    {
        Schema::create('spk_chat_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users', 'id')->onDelete('cascade'); 
            $table->foreignId('spk_id')->constrained('spk_cmt', 'id_spk')->onDelete('cascade'); 
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('spk_chat_invites');
    }
    
}
