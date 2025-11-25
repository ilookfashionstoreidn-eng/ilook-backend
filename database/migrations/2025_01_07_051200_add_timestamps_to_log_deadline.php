<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimestampsToLogDeadline extends Migration
{
    public function up()
    {
        Schema::table('log_deadline', function (Blueprint $table) {
            $table->timestamps();  // created_at dan updated_at
        });
    }

    public function down()
    {
        Schema::table('log_deadline', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at']);
        });
    }
}
