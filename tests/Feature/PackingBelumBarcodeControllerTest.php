<?php

namespace Tests\Feature;

use App\Http\Controllers\PackingBelumBarcodeController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PackingBelumBarcodeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_scan_returns_compact_order_data(): void
    {
        $order = $this->createOrderWithItems([
            'tracking_number' => 'TRACK-PREVIEW-1',
            'order_number' => 'ORDER-PREVIEW-1',
            'total_qty' => 2,
            'status' => 'READY_TO_SHIP',
        ], [
            ['sku' => 'SKU-PREVIEW', 'product_name' => 'Produk Preview', 'quantity' => 2, 'price' => 120000],
        ]);

        $response = app(PackingBelumBarcodeController::class)->showByTracking('TRACK-PREVIEW-1');
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($order->id, $payload['id']);
        $this->assertSame('ORDER-PREVIEW-1', $payload['order_number']);
        $this->assertSame('TRACK-PREVIEW-1', $payload['tracking_number']);
        $this->assertSame('Customer Test', $payload['customer_name']);
        $this->assertSame('08123456789', $payload['customer_phone']);
        $this->assertSame(2, $payload['total_qty']);
        $this->assertSame('READY_TO_SHIP', $payload['status']);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('SKU-PREVIEW', $payload['items'][0]['sku']);
        $this->assertSame('Produk Preview', $payload['items'][0]['product_name']);
        $this->assertSame(2, $payload['items'][0]['quantity']);
    }

    public function test_preview_scan_returns_not_found_for_unknown_tracking_number(): void
    {
        $response = app(PackingBelumBarcodeController::class)->showByTracking('UNKNOWN-TRACK');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Order tidak ditemukan', $response->getData(true)['message']);
    }

    public function test_preview_scan_returns_conflict_when_order_is_already_packed(): void
    {
        $this->createOrderWithItems([
            'tracking_number' => 'TRACK-PACKED-1',
            'order_number' => 'ORDER-PACKED-1',
            'is_packed' => 1,
        ], [
            ['sku' => 'SKU-PACKED', 'product_name' => 'Produk Packed', 'quantity' => 1, 'price' => 90000],
        ]);

        $response = app(PackingBelumBarcodeController::class)->showByTracking('TRACK-PACKED-1');

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'Order ini sudah berstatus packed dan tidak bisa discan ulang.',
            $response->getData(true)['message']
        );
    }

    public function test_submit_records_multiple_orders_to_history_without_marking_them_as_packed(): void
    {
        $orderOne = $this->createOrderWithItems([
            'tracking_number' => 'TRACK-A',
            'order_number' => 'ORDER-A',
            'total_qty' => 2,
        ], [
            ['sku' => 'SKU-A', 'product_name' => 'Produk A', 'quantity' => 2, 'price' => 100000],
        ]);

        $orderTwo = $this->createOrderWithItems([
            'tracking_number' => 'TRACK-B',
            'order_number' => 'ORDER-B',
            'total_qty' => 3,
        ], [
            ['sku' => 'SKU-B', 'product_name' => 'Produk B', 'quantity' => 3, 'price' => 140000],
        ]);

        $response = app(PackingBelumBarcodeController::class)->submit(new Request([
            'scanner_name' => 'Budi Scanner',
            'tracking_numbers' => ['TRACK-A', 'TRACK-B'],
        ]));
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'Semua tracking number berhasil dicatat melalui packing belum barcode',
            $payload['message']
        );
        $this->assertSame(2, $payload['processed_count']);

        $this->assertSame(0, (int) $orderOne->fresh()->is_packed);
        $this->assertSame(0, (int) $orderTwo->fresh()->is_packed);

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $orderOne->id,
            'action' => 'scan_validasi_belum_barcode',
            'performed_by' => 'Budi Scanner',
            'notes' => 'Order berhasil dicatat melalui packing belum barcode dan siap dilanjutkan ke proses packing',
        ]);

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $orderTwo->id,
            'action' => 'scan_validasi_belum_barcode',
            'performed_by' => 'Budi Scanner',
            'notes' => 'Order berhasil dicatat melalui packing belum barcode dan siap dilanjutkan ke proses packing',
        ]);
    }

    public function test_submit_rolls_back_when_one_tracking_is_invalid(): void
    {
        $orderOne = $this->createOrderWithItems([
            'tracking_number' => 'TRACK-ROLLBACK-A',
            'order_number' => 'ORDER-ROLLBACK-A',
            'total_qty' => 2,
        ], [
            ['sku' => 'SKU-ROLLBACK-A', 'product_name' => 'Produk Rollback A', 'quantity' => 2, 'price' => 110000],
        ]);

        $orderTwo = $this->createOrderWithItems([
            'tracking_number' => 'TRACK-ROLLBACK-B',
            'order_number' => 'ORDER-ROLLBACK-B',
            'total_qty' => 2,
        ], [
            ['sku' => 'SKU-ROLLBACK-B', 'product_name' => 'Produk Rollback B', 'quantity' => 2, 'price' => 130000],
        ]);

        $response = app(PackingBelumBarcodeController::class)->submit(new Request([
            'scanner_name' => 'Scanner Rollback',
            'tracking_numbers' => ['TRACK-ROLLBACK-A', 'TRACK-TIDAK-ADA'],
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'Order dengan tracking number TRACK-TIDAK-ADA tidak ditemukan',
            $response->getData(true)['message']
        );

        $this->assertSame(0, (int) $orderOne->fresh()->is_packed);
        $this->assertSame(0, (int) $orderTwo->fresh()->is_packed);
        $this->assertSame(0, OrderLog::count());
    }

    public function test_preview_scan_returns_conflict_when_order_was_already_submitted_in_belum_barcode_flow(): void
    {
        $order = $this->createOrderWithItems([
            'tracking_number' => 'TRACK-HISTORY-1',
            'order_number' => 'ORDER-HISTORY-1',
        ], [
            ['sku' => 'SKU-HISTORY', 'product_name' => 'Produk History', 'quantity' => 1, 'price' => 75000],
        ]);

        OrderLog::create([
            'order_id' => $order->id,
            'action' => 'scan_validasi_belum_barcode',
            'performed_by' => 'Scanner Lama',
            'notes' => 'Order berhasil dicatat melalui packing belum barcode dan siap dilanjutkan ke proses packing',
        ]);

        $response = app(PackingBelumBarcodeController::class)->showByTracking('TRACK-HISTORY-1');

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            'Order ini sudah pernah disubmit melalui packing belum barcode dan siap dilanjutkan ke proses packing.',
            $response->getData(true)['message']
        );
    }

    private function createOrderWithItems(array $orderAttributes, array $items): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORDER-' . uniqid(),
            'tracking_number' => 'TRACK-' . uniqid(),
            'platform' => 'Shopee',
            'customer_name' => 'Customer Test',
            'customer_phone' => '08123456789',
            'total_amount' => 250000,
            'status' => 'READY_TO_SHIP',
            'order_date' => now(),
            'total_qty' => collect($items)->sum('quantity'),
            'is_packed' => 0,
        ], $orderAttributes));

        foreach ($items as $item) {
            OrderItem::create(array_merge([
                'order_id' => $order->id,
                'sku' => 'SKU-' . uniqid(),
                'product_name' => 'Produk Test',
                'quantity' => 1,
                'price' => 100000,
            ], $item));
        }

        return $order;
    }
}
