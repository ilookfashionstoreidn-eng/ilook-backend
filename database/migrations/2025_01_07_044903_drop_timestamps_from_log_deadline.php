<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropTimestampsFromLogDeadline extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('log_deadline', function (Blueprint $table) {
            if (Schema::hasColumn('log_deadline', 'created_at')) {
                $table->dropColumn('created_at');
            }
            if (Schema::hasColumn('log_deadline', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
        
    }
    
    public function down()
    {
        Schema::table('log_deadline', function (Blueprint $table) {
            $table->timestamps();
        });
    }
    
}
