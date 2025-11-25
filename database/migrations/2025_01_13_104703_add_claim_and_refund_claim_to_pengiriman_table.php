<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClaimAndRefundClaimToPengirimanTable extends Migration
{
    
    public function up()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->decimal('claim', 15, 2)->default(0); 
            $table->decimal('refund_claim', 15, 2)->default(0);  
        });
    }

    
    public function down()
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropColumn('claim');
            $table->dropColumn('refund_claim');
        });
    }
}
