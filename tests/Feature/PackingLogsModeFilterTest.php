<?php

namespace Tests\Feature;

use App\Exports\OrderLogsExport;
use App\Http\Controllers\OrderController;
use App\Models\NoDataGineeLog;
use App\Models\Order;
use App\Models\OrderLog;
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
        $this->assertSame(0, $payload['data'][0]['total_items']);
        $this->assertSame(0, $payload['data'][0]['total_amount']);
        $this->assertCount(1, $payload['kasir_summary']);
        $this->assertSame('Scanner NDG', $payload['kasir_summary'][0]['performed_by']);
        $this->assertSame(1, $payload['kasir_summary'][0]['total_orders']);
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
