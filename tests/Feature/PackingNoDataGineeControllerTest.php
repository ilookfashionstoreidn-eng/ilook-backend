<?php

namespace Tests\Feature;

use App\Http\Controllers\PackingNoDataGineeController;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PackingNoDataGineeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_rejects_tracking_when_order_already_exists(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORDER-NDG-BLOCK-1',
            'tracking_number' => 'TRACK-NDG-BLOCK-1',
        ]);

        $response = app(PackingNoDataGineeController::class)->check($order->tracking_number);
        $payload = $response->getData(true);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertFalse($payload['already_scanned']);
        $this->assertTrue($payload['order_found']);
        $this->assertTrue($payload['order_already_available']);
        $this->assertSame($order->order_number, $payload['order']['order_number']);
        $this->assertStringContainsString(
            'tidak boleh discan melalui No Data Ginee',
            $payload['message']
        );
    }

    public function test_tracking_only_submit_rejects_tracking_when_order_already_exists(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORDER-NDG-BLOCK-2',
            'tracking_number' => 'TRACK-NDG-BLOCK-2',
        ]);

        $response = app(PackingNoDataGineeController::class)->submit(
            Request::create('/api/packing-no-data-ginee/submit', 'POST', [
                'scanner_name' => 'Scanner Test',
                'tracking_numbers' => [$order->tracking_number],
            ])
        );
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString(
            'tidak boleh discan melalui No Data Ginee',
            $payload['message']
        );
        $this->assertDatabaseMissing('no_data_ginee_logs', [
            'tracking_number' => $order->tracking_number,
        ]);
        $this->assertDatabaseHas('order', [
            'id' => $order->id,
            'is_packed' => 0,
        ]);
    }

    public function test_serial_submit_rejects_tracking_when_order_already_exists(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORDER-NDG-BLOCK-3',
            'tracking_number' => 'TRACK-NDG-BLOCK-3',
        ]);

        $response = app(PackingNoDataGineeController::class)->submit(
            Request::create('/api/packing-no-data-ginee/submit', 'POST', [
                'scan_mode' => 'serial_scan',
                'scanner_name' => 'Scanner Test',
                'tracking_number' => $order->tracking_number,
                'items' => [
                    [
                        'actual_sku' => 'SKU-001',
                        'serial_number' => 'SERIAL-001',
                    ],
                ],
            ])
        );
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString(
            'tidak boleh discan melalui No Data Ginee',
            $payload['message']
        );
        $this->assertDatabaseMissing('no_data_ginee_logs', [
            'tracking_number' => $order->tracking_number,
        ]);
    }

    public function test_tracking_only_submit_allows_tracking_when_order_is_missing(): void
    {
        $trackingNumber = 'TRACK-NDG-MISSING-1';

        $response = app(PackingNoDataGineeController::class)->submit(
            Request::create('/api/packing-no-data-ginee/submit', 'POST', [
                'scanner_name' => 'Scanner Test',
                'tracking_numbers' => [$trackingNumber],
            ])
        );
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $payload['processed_count']);
        $this->assertDatabaseHas('no_data_ginee_logs', [
            'tracking_number' => $trackingNumber,
            'scanner_name' => 'Scanner Test',
            'scan_mode' => 'tracking_only',
            'order_id' => null,
        ]);
    }

    private function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORDER-' . uniqid(),
            'tracking_number' => 'TRACK-' . uniqid(),
            'platform' => 'Shopee',
            'customer_name' => 'Customer Test',
            'customer_phone' => '08123456789',
            'total_amount' => 150000,
            'status' => 'READY_TO_SHIP',
            'order_date' => now(),
            'total_qty' => 1,
            'is_packed' => 0,
        ], $attributes));
    }
}
