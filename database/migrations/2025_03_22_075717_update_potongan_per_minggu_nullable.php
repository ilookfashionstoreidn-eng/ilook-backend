<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePotonganPerMingguNullable extends Migration
{
    public function up()
    {
        Schema::table('hutang', function (Blueprint $table) {
            $table->decimal('potongan_per_minggu', 15, 2)->nullable()->change();
        });
    }
    
    public function down()
    {
        Schema::table('hutang', function (Blueprint $table) {
            $table->decimal('potongan_per_minggu', 15, 2)->nullable(false)->change();
        });
    }
    
}
