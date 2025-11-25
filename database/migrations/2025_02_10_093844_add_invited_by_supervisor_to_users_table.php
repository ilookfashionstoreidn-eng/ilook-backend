<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInvitedBySupervisorToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('invited_by_supervisor')->default(false);
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('invited_spk_id')->nullable();
            $table->foreign('invited_spk_id')->references('id_spk')->on('spk_cmt')->onDelete('set null');
        });
        
    }
    


public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('invited_by_supervisor')->default(false);
        $table->unsignedBigInteger('invited_spk_id')->nullable();
        $table->foreign('invited_spk_id')->references('id_spk')->on('spk_cmt')->onDelete('set null');
    });
}

}    