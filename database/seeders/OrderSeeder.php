<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderItem;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $order = Order::create([
            'order_number' => 'SO-001',
            'tracking_number' => 'TK123456789',
            'platform' => 'Shopee',
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'total_amount' => 150000,
            'status' => 'ready_to_pack',
            'order_date' => now(),
        ]);

        $order->items()->createMany([
            ['sku' => 'A001', 'product_name' => 'Kaos Putih', 'quantity' => 2, 'price' => 50000],
            ['sku' => 'B002', 'product_name' => 'Celana Pendek', 'quantity' => 1, 'price' => 50000],
        ]);
    }
}
