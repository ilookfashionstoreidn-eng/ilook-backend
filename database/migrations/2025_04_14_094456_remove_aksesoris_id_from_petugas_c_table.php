<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveAksesorisIdFromPetugasCTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('petugas_c', function (Blueprint $table) {
            $table->dropForeign(['aksesoris_id']);
        });
    
        Schema::table('petugas_c', function (Blueprint $table) {
            $table->dropColumn('aksesoris_id');
        });
    }
    
    
    public function down()
    {
        Schema::table('petugas_c', function (Blueprint $table) {
            $table->foreignId('aksesoris_id')->constrained('aksesoris')->onDelete('cascade');
        });
    }
    
}
