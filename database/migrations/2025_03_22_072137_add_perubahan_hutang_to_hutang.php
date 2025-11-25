<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerubahanHutangToHutang extends Migration
{
    
    public function up()
    {
        Schema::table('hutang', function (Blueprint $table) {
            $table->decimal('perubahan_hutang', 15, 2)->nullable();
        });
    }
    

    public function down()
    {
        Schema::table('hutang', function (Blueprint $table) {
            $table->dropColumn('perubahan_hutang');
        });
    }
}
