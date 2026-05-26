<?php

namespace Tests\Feature;

use App\Exports\OrderLogsExport;
use App\Http\Controllers\OrderController;
use App\Models\NoDataGineeLog;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderPackingResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PackingLogsModeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_all_logs_filters_only_random_mode(): void
    {
        $normalOrder = $this->createOrder([
            'order_number' => 'ORDER-NORMAL-1',
            'tracking_number' => 'TRACK-NORMAL-1',
        ]);

        $randomOrder = $this->createOrder([
            'order_number' => 'ORDER-RANDOM-1',
            'tracking_number' => 'TRACK-RANDOM-1',
        ]);

        OrderLog::create([
            'order_id' => $normalOrder->id,
            'action' => 'scan_validasi',
            'performed_by' => 'Scanner Normal',
            'notes' => 'Normal mode',
        ]);

        OrderLog::create([
            'order_id' => $randomOrder->id,
            'action' => 'scan_validasi_random',
            'performed_by' => 'Scanner Random',
            'notes' => 'Random mode',
        ]);

        NoDataGineeLog::create([
            'tracking_number' => 'TRACK-NDG-1',
            'scanner_name' => 'Scanner NDG',
            'notes' => 'No Data Ginee mode',
        ]);

        $response = app(OrderController::class)->getAllLogs(
            Request::create('/api/orders/logs', 'GET', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'mode' => 'random',
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']);
        $this->assertSame('scan_validasi_random', $payload['data'][0]['action']);
        $this->assertSame('TRACK-RANDOM-1', $payload['data'][0]['order']['tracking_number']);
    }

    public function test_get_all_logs_filters_only_pendingan_mode(): void
    {
        $randomOrder = $this->createOrder([
            'order_number' => 'ORDER-RANDOM-PENDINGAN-EXCLUDE',
            'tracking_number' => 'TRACK-RANDOM-PENDINGAN-EXCLUDE',
        ]);

        $pendinganOrder = $this->createOrder([
            'order_number' => 'ORDER-PENDINGAN-1',
            'tracking_number' => 'TRACK-PENDINGAN-1',
        ]);

        OrderLog::create([
            'order_id' => $randomOrder->id,
            'action' => 'scan_validasi_random',
            'performed_by' => 'Scanner Random',
            'notes' => 'Random mode',
        ]);

        OrderLog::create([
            'order_id' => $pendinganOrder->id,
            'action' => 'scan_validasi_pendingan',
            'performed_by' => 'Scanner Pendingan',
            'notes' => 'Pendingan mode',
        ]);

        $response = app(OrderController::class)->getAllLogs(
            Request::create('/api/orders/logs', 'GET', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'mode' => 'pendingan',
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $payload['data']);
        $this->assertSame('scan_validasi_pendingan', $payload['data'][0]['action']);
        $this->assertSame('Pendingan', $payload['data'][0]['mode_label']);
        $this->assertSame('TRACK-PENDINGAN-1', $payload['data'][0]['order']['tracking_number']);
    }

    public function test_summary_report_filters_pendingan_mode_and_uses_packing_result_qty(): void
    {
        $pendinganOrder = $this->createOrder([
            'order_number' => 'ORDER-PENDINGAN-SUMMARY',
            'tracking_number' => 'TRACK-PENDINGAN-SUMMARY',
            'total_qty' => 1,
        ]);

        OrderPackingResult::create([
            'order_id' => $pendinganOrder->id,
            'line_type' => 'order_item',
            'status' => 'pendingan',
            'original_sku' => 'SKU-ORDER',
            'original_product_name' => 'Produk Order',
            'actual_sku' => 'SKU-PENDINGAN',
            'actual_product_name' => 'Produk Pendingan',
            'ordered_qty' => 2,
            'scanned_qty' => 2,
        ]);

        OrderLog::create([
            'order_id' => $this->createOrder([
                'order_number' => 'ORDER-NORMAL-PENDINGAN-EXCLUDE',
                'tracking_number' => 'TRACK-NORMAL-PENDINGAN-EXCLUDE',
            ])->id,
            'action' => 'scan_validasi',
            'performed_by' => 'Scanner Normal',
            'notes' => 'Normal mode',
        ]);

        OrderLog::create([
            'order_id' => $pendinganOrder->id,
            'action' => 'scan_validasi_pendingan',
            'performed_by' => 'Scanner Pendingan',
            'notes' => 'Pendingan mode',
        ]);

        $response = app(OrderController::class)->getSummaryReport(
            Request::create('/api/orders/summary', 'POST', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'mode' => 'pendingan',
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pendingan', $payload['filters']['mode']);
        $this->assertSame(1, $payload['data'][0]['total_order']);
        $this->assertSame(2, $payload['data'][0]['total_items']);
        $this->assertSame('2', $payload['data'][0]['total_items_formatted']);
        $this->assertCount(1, $payload['kasir_summary']);
        $this->assertSame('Scanner Pendingan', $payload['kasir_summary'][0]['performed_by']);
    }

    public function test_summary_report_filters_only_no_data_ginee_mode(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORDER-SUMMARY-1',
            'tracking_number' => 'TRACK-SUMMARY-1',
        ]);

        OrderLog::create([
            'order_id' => $order->id,
            'action' => 'scan_validasi',
            'performed_by' => 'Scanner Normal',
            'notes' => 'Normal mode',
        ]);

        NoDataGineeLog::create([
            'tracking_number' => 'TRACK-NDG-2',
            'scanner_name' => 'Scanner NDG',
            'notes' => 'No Data Ginee mode',
        ]);

        $response = app(OrderController::class)->getSummaryReport(
            Request::create('/api/orders/summary', 'POST', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'mode' => 'no-data-ginee',
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('no-data-ginee', $payload['filters']['mode']);
        $this->assertSame(1, $payload['data'][0]['total_order']);
        $this->assertSame('1', $payload['data'][0]['total_order_formatted']);
        $this->assertSame(0, $payload['data'][0]['total_items']);
        $this->assertSame('0', $payload['data'][0]['total_items_formatted']);
        $this->assertSame(0, $payload['data'][0]['total_amount']);
        $this->assertSame('0', $payload['data'][0]['total_amount_formatted']);
        $this->assertCount(1, $payload['kasir_summary']);
        $this->assertSame('Scanner NDG', $payload['kasir_summary'][0]['performed_by']);
        $this->assertSame(1, $payload['kasir_summary'][0]['total_orders']);
        $this->assertSame('1', $payload['kasir_summary'][0]['total_orders_formatted']);
    }

    public function test_summary_report_includes_formatted_number_fields_for_thousands(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORDER-FORMAT-1',
            'tracking_number' => 'TRACK-FORMAT-1',
            'total_amount' => 1234567,
            'total_qty' => 6528,
        ]);

        OrderLog::create([
            'order_id' => $order->id,
            'action' => 'scan_validasi',
            'performed_by' => 'Scanner Format',
            'notes' => 'Summary format test',
        ]);

        $response = app(OrderController::class)->getSummaryReport(
            Request::create('/api/orders/summary', 'POST', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'mode' => 'normal',
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(6528, $payload['data'][0]['total_items']);
        $this->assertSame('6.528', $payload['data'][0]['total_items_formatted']);
        $this->assertSame(1234567, $payload['data'][0]['total_amount']);
        $this->assertSame('1.234.567', $payload['data'][0]['total_amount_formatted']);
    }

    public function test_get_all_logs_filters_multiple_modes(): void
    {
        $normalOrder = $this->createOrder([
            'order_number' => 'ORDER-MULTI-NORMAL',
            'tracking_number' => 'TRACK-MULTI-NORMAL',
        ]);

        $randomOrder = $this->createOrder([
            'order_number' => 'ORDER-MULTI-RANDOM',
            'tracking_number' => 'TRACK-MULTI-RANDOM',
        ]);

        OrderLog::create([
            'order_id' => $normalOrder->id,
            'action' => 'scan_validasi',
            'performed_by' => 'Scanner Normal Multi',
            'notes' => 'Normal multi mode',
        ]);

        OrderLog::create([
            'order_id' => $randomOrder->id,
            'action' => 'scan_validasi_random',
            'performed_by' => 'Scanner Random Multi',
            'notes' => 'Random multi mode',
        ]);

        NoDataGineeLog::create([
            'tracking_number' => 'TRACK-MULTI-NDG',
            'scanner_name' => 'Scanner NDG Multi',
            'notes' => 'NDG should be excluded',
        ]);

        $response = app(OrderController::class)->getAllLogs(
            Request::create('/api/orders/logs', 'GET', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'mode' => ['normal', 'random'],
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $payload['data']);
        $this->assertEqualsCanonicalizing(
            ['scan_validasi', 'scan_validasi_random'],
            array_column($payload['data'], 'action')
        );
    }

    public function test_summary_report_filters_multiple_modes_including_no_data_ginee(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORDER-MULTI-SUMMARY',
            'tracking_number' => 'TRACK-MULTI-SUMMARY',
        ]);

        OrderLog::create([
            'order_id' => $order->id,
            'action' => 'scan_validasi',
            'performed_by' => 'Scanner Normal Summary',
            'notes' => 'Normal summary mode',
        ]);

        OrderLog::create([
            'order_id' => $this->createOrder([
                'order_number' => 'ORDER-RANDOM-EXCLUDED',
                'tracking_number' => 'TRACK-RANDOM-EXCLUDED',
            ])->id,
            'action' => 'scan_validasi_random',
            'performed_by' => 'Scanner Random Excluded',
            'notes' => 'Random mode should be excluded',
        ]);

        NoDataGineeLog::create([
            'tracking_number' => 'TRACK-MULTI-SUMMARY-NDG',
            'scanner_name' => 'Scanner NDG Summary',
            'notes' => 'NDG summary mode',
        ]);

        $response = app(OrderController::class)->getSummaryReport(
            Request::create('/api/orders/summary', 'POST', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'mode' => ['normal', 'no-data-ginee'],
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['normal', 'no-data-ginee'], $payload['filters']['mode']);
        $this->assertSame(2, $payload['data'][0]['total_order']);
        $this->assertCount(2, $payload['kasir_summary']);
        $this->assertEqualsCanonicalizing(
            ['Scanner Normal Summary', 'Scanner NDG Summary'],
            array_column($payload['kasir_summary'], 'performed_by')
        );
    }

    public function test_export_collection_filters_and_includes_no_data_ginee_mode(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORDER-EXPORT-1',
            'tracking_number' => 'TRACK-EXPORT-1',
        ]);

        OrderLog::create([
            'order_id' => $order->id,
            'action' => 'scan_validasi',
            'performed_by' => 'Scanner Normal',
            'notes' => 'Normal mode',
        ]);

        NoDataGineeLog::create([
            'tracking_number' => 'TRACK-NDG-EXPORT',
            'scanner_name' => 'Scanner NDG Export',
            'notes' => 'No Data Ginee export mode',
        ]);

        $rows = (new OrderLogsExport(
            now()->startOfDay(),
            now()->endOfDay(),
            ['mode' => 'no-data-ginee']
        ))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame('scan_no_data_ginee', $rows->first()->action);
        $this->assertSame('TRACK-NDG-EXPORT', $rows->first()->order->tracking_number);
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
