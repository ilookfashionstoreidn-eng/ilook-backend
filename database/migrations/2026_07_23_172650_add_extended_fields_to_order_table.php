<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtendedFieldsToOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order', function (Blueprint $table) {
            $table->string('logistic_provider_name', 100)->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_city', 150)->nullable();
            $table->string('customer_province', 150)->nullable();
            $table->string('customer_zip_code', 20)->nullable();
            $table->decimal('shipping_fee', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->string('voucher_code', 100)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->dateTime('pay_time')->nullable();
            $table->dateTime('cancel_time')->nullable();
            $table->text('buyer_message')->nullable();
            $table->text('seller_memo')->nullable();
            $table->string('cancel_reason', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn([
                'logistic_provider_name',
                'customer_address',
                'customer_city',
                'customer_province',
                'customer_zip_code',
                'shipping_fee',
                'discount_amount',
                'voucher_code',
                'tax_amount',
                'pay_time',
                'cancel_time',
                'buyer_message',
                'seller_memo',
                'cancel_reason'
            ]);
        });
    }
}
