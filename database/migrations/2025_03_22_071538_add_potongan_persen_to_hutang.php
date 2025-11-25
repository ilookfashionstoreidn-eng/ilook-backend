<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPotonganPersenToHutang extends Migration
{
    public function up()
    {
        Schema::table('hutang', function (Blueprint $table) {
            $table->boolean('is_potongan_persen')->default(false);
            $table->decimal('persentase_potongan', 5, 2)->nullable(); 
        });
    }
    

    public function down()
    {
        Schema::table('hutang', function (Blueprint $table) {
            $table->dropColumn(['is_potongan_persen', 'persentase_potongan']);
        });
    }
}
