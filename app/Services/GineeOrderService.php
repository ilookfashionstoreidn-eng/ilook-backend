<?php


namespace App\Services;


use App\Models\NoDataGineeLog;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Helpers\GineeSignature;
use App\Models\SyncLog;


class GineeOrderService
{
    public function __construct(
        private NoDataGineeSerialPackingService $noDataGineeSerialPackingService
    ) {
    }

    private function getApiContext(): array
    {
        $accessKey = env('GINEE_ACCESS_KEY');
        $secretKey = env('GINEE_SECRET_KEY');
        $country = env('GINEE_COUNTRY', 'ID');
        $host = env('GINEE_API_HOST', 'https://api.ginee.com');

        $endpointList = '/openapi/order/v2/list-order';
        $endpointBatch = '/openapi/order/v1/batch-get';

        $signatureList = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointList, $secretKey));

        $headers = [
            'Content-Type' => 'application/json',
            'X-Advai-Country' => $country,
            'Authorization' => $accessKey . ':' . $signatureList
        ];

        return compact(
            'accessKey',
            'secretKey',
            'country',
            'host',
            'endpointList',
            'endpointBatch',
            'headers'
        );
    }

    private function getDailyRepairWindowDays(): int
    {
        $days = (int) env('GINEE_DAILY_REPAIR_DAYS', 14);

        return max(1, min($days, 90));
    }

    private function getPrintedInitialLookbackHours(): int
    {
        $hours = (int) env('GINEE_PRINTED_INITIAL_LOOKBACK_HOURS', 12);

        return max(1, min($hours, 72));
    }

    private function getPrintedSyncBufferMinutes(): int
    {
        $minutes = (int) env('GINEE_PRINTED_SYNC_BUFFER_MINUTES', 30);

        return max(1, min($minutes, 30));
    }

    private function getPackingHotPrintedBufferMinutes(): int
    {
        $minutes = (int) env('GINEE_PACKING_HOT_PRINTED_BUFFER_MINUTES', 60);

        return max(5, min($minutes, 180));
    }

    private function getPackingHotReadyToShipBufferMinutes(): int
    {
        $minutes = (int) env('GINEE_PACKING_HOT_READY_TO_SHIP_BUFFER_MINUTES', 180);

        return max(5, min($minutes, 360));
    }

    private function getPrintedCatchupHours(): int
    {
        $hours = (int) env('GINEE_PRINTED_CATCHUP_HOURS', 12);

        return max(1, min($hours, 24));
    }

    private function getReadyToShipRepairLookbackHours(): int
    {
        $hours = (int) env('GINEE_READY_TO_SHIP_REPAIR_LOOKBACK_HOURS', 12);

        return max(1, min($hours, 48));
    }

    private function buildSyncWindow(string $type, Carbon $defaultLastSyncAt, int $bufferMinutes): array
    {
        $syncLog = SyncLog::firstOrCreate(
            ['type' => $type],
            ['last_sync_at' => $defaultLastSyncAt]
        );

        $since = Carbon::parse($syncLog->last_sync_at)
            ->subMinutes($bufferMinutes)
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');

        $to = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return [$syncLog, $since, $to];
    }

    public function syncRecentOrders(): array
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        extract($this->getApiContext());

        [$syncLog, $since, $to] = $this->buildSyncWindow(
            'orders',
            now()->subHours(12),
            180
        );

        $totalProcessed = 0;
        $newCount = 0;
        $updatedCount = 0;

        /*
        =================================
        1. READY TO SHIP
        Fokus ke order yang memang relevan untuk gudang.
        =================================
        */

        try {
            $this->syncOrderByCursor([
                'orderStatus' => 'READY_TO_SHIP',
                'lastUpdateSince' => $since,
                'lastUpdateTo' => $to,
            ], $headers, $endpointList, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed);
        } catch (\Throwable $e) {
            Log::error('Sinkronisasi order Ginee gagal', [
                'type' => 'orders',
                'since' => $since,
                'to' => $to,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $syncLog->update([
            'last_sync_at' => now()
        ]);

        return [
            'totalProcessed' => $totalProcessed,
            'new' => $newCount,
            'updated' => $updatedCount,
        ];
    }

    public function syncPrintedOrders(): array
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        extract($this->getApiContext());

        [$syncLog, $since, $to] = $this->buildSyncWindow(
            'orders_printed',
            now()->subHours($this->getPrintedInitialLookbackHours()),
            $this->getPrintedSyncBufferMinutes()
        );

        try {
            $result = $this->syncPrintedOrderWindow(
                $since,
                $to,
                $headers,
                $endpointList,
                $endpointBatch,
                $accessKey,
                $secretKey,
                $country,
                $host
            );
        } catch (\Throwable $e) {
            Log::error('Sinkronisasi order PRINTED Ginee gagal', [
                'type' => 'orders_printed',
                'since' => $since,
                'to' => $to,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $syncLog->update([
            'last_sync_at' => now()
        ]);

        return $result;
    }

    public function syncPackingHot(): array
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        extract($this->getApiContext());

        [$printedSyncLog, $printedSince, $printedTo] = $this->buildSyncWindow(
            'orders_packing_hot_printed',
            now()->subHours($this->getPrintedInitialLookbackHours()),
            $this->getPackingHotPrintedBufferMinutes()
        );

        try {
            $printedResult = $this->syncPrintedOrderWindow(
                $printedSince,
                $printedTo,
                $headers,
                $endpointList,
                $endpointBatch,
                $accessKey,
                $secretKey,
                $country,
                $host
            );
        } catch (\Throwable $e) {
            Log::error('Hot sync PRINTED Ginee gagal', [
                'type' => 'orders_packing_hot_printed',
                'since' => $printedSince,
                'to' => $printedTo,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $printedSyncLog->update([
            'last_sync_at' => now()
        ]);

        [$readySyncLog, $readySince, $readyTo] = $this->buildSyncWindow(
            'orders_packing_hot_ready_to_ship',
            now()->subHours(12),
            $this->getPackingHotReadyToShipBufferMinutes()
        );

        try {
            $readyToShipResult = $this->syncReadyToShipOrderWindow(
                $readySince,
                $readyTo,
                $headers,
                $endpointList,
                $endpointBatch,
                $accessKey,
                $secretKey,
                $country,
                $host
            );
        } catch (\Throwable $e) {
            Log::error('Hot sync READY_TO_SHIP Ginee gagal', [
                'type' => 'orders_packing_hot_ready_to_ship',
                'since' => $readySince,
                'to' => $readyTo,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $readySyncLog->update([
            'last_sync_at' => now()
        ]);

        SyncLog::updateOrCreate(
            ['type' => 'orders_packing_hot'],
            ['last_sync_at' => now()]
        );

        return $this->buildCombinedResult([
            'printed' => $printedResult,
            'ready_to_ship' => $readyToShipResult,
        ]);
    }

    public function syncPrintedCatchup($hours = null): array
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        $catchupHours = $hours !== null ? (int) $hours : $this->getPrintedCatchupHours();
        $catchupHours = max(1, min($catchupHours, 24));

        $from = now()->subHours($catchupHours);
        $to = now();

        extract($this->getApiContext());

        try {
            $result = $this->syncPrintedOrderWindow(
                $from->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                $to->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                $headers,
                $endpointList,
                $endpointBatch,
                $accessKey,
                $secretKey,
                $country,
                $host,
                true
            );
        } catch (\Throwable $e) {
            Log::error('Catch-up sync PRINTED Ginee gagal', [
                'type' => 'orders_printed_catchup',
                'since' => $from->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'to' => $to->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        SyncLog::updateOrCreate(
            ['type' => 'orders_printed_catchup'],
            ['last_sync_at' => now()]
        );

        $result['hours'] = $catchupHours;
        $result['from'] = $from->format('Y-m-d H:i:s');
        $result['to'] = $to->format('Y-m-d H:i:s');

        return $result;
    }

    public function syncReadyToShipCreateDatePool($days = null): array
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        $poolDays = $days !== null ? (int) $days : 3;
        $poolDays = max(1, min($poolDays, 30));

        $fromDate = now()->subDays($poolDays - 1)->startOfDay();
        $toDate = now();

        extract($this->getApiContext());

        $totalProcessed = 0;
        $newCount = 0;
        $updatedCount = 0;

        $windowStart = $fromDate->copy();

        try {
            while ($windowStart->lte($toDate)) {
                $windowEnd = $windowStart->copy()->addDays(2)->endOfDay();

                if ($windowEnd->gt($toDate)) {
                    $windowEnd = $toDate->copy();
                }

                $this->syncOrderByCursor([
                    'orderStatus' => 'READY_TO_SHIP',
                    'createSince' => $windowStart->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'createTo' => $windowEnd->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                ], $headers, $endpointList, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed);

                $windowStart = $windowEnd->copy()->addSecond();
            }
        } catch (\Throwable $e) {
            Log::error('Pool sinkronisasi order READY_TO_SHIP Ginee gagal', [
                'type' => "orders_ready_to_ship_pool_{$poolDays}d",
                'from' => $fromDate->format('Y-m-d H:i:s'),
                'to' => $toDate->format('Y-m-d H:i:s'),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        SyncLog::updateOrCreate(
            ['type' => "orders_ready_to_ship_pool_{$poolDays}d"],
            ['last_sync_at' => now()]
        );

        return [
            'totalProcessed' => $totalProcessed,
            'new' => $newCount,
            'updated' => $updatedCount,
            'days' => $poolDays,
            'from' => $fromDate->format('Y-m-d H:i:s'),
            'to' => $toDate->format('Y-m-d H:i:s'),
        ];
    }

    public function syncReadyToShipRepairWindow($hours = null): array
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        $repairHours = $hours !== null ? (int) $hours : $this->getReadyToShipRepairLookbackHours();
        $repairHours = max(1, min($repairHours, 48));

        $from = now()->subHours($repairHours);
        $to = now();

        extract($this->getApiContext());

        try {
            $result = $this->syncReadyToShipOrderWindow(
                $from->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                $to->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                $headers,
                $endpointList,
                $endpointBatch,
                $accessKey,
                $secretKey,
                $country,
                $host,
                true
            );
        } catch (\Throwable $e) {
            Log::error('Repair sinkronisasi order READY_TO_SHIP Ginee gagal', [
                'type' => 'orders_ready_to_ship_repair',
                'since' => $from->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'to' => $to->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        SyncLog::updateOrCreate(
            ['type' => 'orders_ready_to_ship_repair'],
            ['last_sync_at' => now()]
        );

        $result['hours'] = $repairHours;
        $result['from'] = $from->format('Y-m-d H:i:s');
        $result['to'] = $to->format('Y-m-d H:i:s');

        return $result;
    }

    public function syncCreateDateRange($from, $to = null, bool $yieldToHotSync = false): array
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to ?? $from)->endOfDay();

        if ($fromDate->gt($toDate)) {
            throw new \InvalidArgumentException('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.');
        }

        $oldestAllowedDate = now()->subMonths(3)->startOfDay();

        if ($fromDate->lt($oldestAllowedDate)) {
            throw new \InvalidArgumentException(
                'Ginee ListOrder hanya menyediakan order 3 bulan terakhir. Gunakan tanggal mulai ' . $oldestAllowedDate->toDateString() . ' atau setelahnya.'
            );
        }

        extract($this->getApiContext());

        $totalProcessed = 0;
        $newCount = 0;
        $updatedCount = 0;

        $windowStart = $fromDate->copy();

        while ($windowStart->lte($toDate)) {
            $windowEnd = $windowStart->copy()->addDays(14)->endOfDay();

            if ($windowEnd->gt($toDate)) {
                $windowEnd = $toDate->copy();
            }

            $this->syncOrderByCursor([
                'createSince' => $windowStart->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'createTo' => $windowEnd->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            ], $headers, $endpointList, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed, $yieldToHotSync);

            if ($yieldToHotSync && $this->isHotSyncRunning()) {
                break;
            }

            $windowStart = $windowEnd->copy()->addSecond();
        }

        return [
            'totalProcessed' => $totalProcessed,
            'new' => $newCount,
            'updated' => $updatedCount,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
        ];
    }

    public function syncDailyRepairWindow($days = null): array
    {
        $repairDays = $days !== null ? (int) $days : $this->getDailyRepairWindowDays();
        $repairDays = max(1, min($repairDays, 90));

        $result = $this->syncCreateDateRange(
            now()->subDays($repairDays - 1)->startOfDay(),
            now()
        );

        $result['days'] = $repairDays;

        return $result;
    }

    private function buildCombinedResult(array $details): array
    {
        $totalProcessed = 0;
        $newCount = 0;
        $updatedCount = 0;

        foreach ($details as $result) {
            $totalProcessed += (int) ($result['totalProcessed'] ?? 0);
            $newCount += (int) ($result['new'] ?? 0);
            $updatedCount += (int) ($result['updated'] ?? 0);
        }

        return [
            'totalProcessed' => $totalProcessed,
            'new' => $newCount,
            'updated' => $updatedCount,
            'details' => $details,
        ];
    }

    private function isHotSyncRunning(): bool
    {
        return Cache::has('ginee-hot-sync-running');
    }

    private function syncOrderByCursor(
        $params,
        $headers,
        $endpointList,
        $endpointBatch,
        $accessKey,
        $secretKey,
        $country,
        $host,
        &$newCount,
        &$updatedCount,
        &$totalProcessed,
        bool $yieldToHotSync = false
    ) {

        $nextCursor = null;

        do {
            if ($yieldToHotSync && $this->isHotSyncRunning()) {
                break;
            }

            $body = $params + [
                'size' => 100
            ];

            if ($nextCursor) {
                $body['nextCursor'] = $nextCursor;
            }

            $response = $this->postToGinee($host, $endpointList, $headers, $body);

            $listData = $response['data']['content'] ?? [];
            $hasMore = $response['data']['more'] ?? false;
            $nextCursor = $response['data']['nextCursor'] ?? null;

            if (!empty($listData)) {

                $this->saveOrderBatch(
                    $listData,
                    $endpointBatch,
                    $accessKey,
                    $secretKey,
                    $country,
                    $host,
                    $newCount,
                    $updatedCount,
                    $totalProcessed,
                    $yieldToHotSync
                );

            }

            usleep(200000); // anti rate limit

        } while ($hasMore);
    }

    private function syncPrintedOrderWindow(
        string $since,
        string $to,
        array $headers,
        string $endpointList,
        string $endpointBatch,
        string $accessKey,
        string $secretKey,
        string $country,
        string $host,
        bool $yieldToHotSync = false
    ): array {
        $totalProcessed = 0;
        $newCount = 0;
        $updatedCount = 0;

        $this->syncOrderByCursor([
            'labelPrintStatus' => 'PRINTED',
            'labelPrintTimeSince' => $since,
            'labelPrintTimeTo' => $to,
        ], $headers, $endpointList, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed, $yieldToHotSync);

        return [
            'totalProcessed' => $totalProcessed,
            'new' => $newCount,
            'updated' => $updatedCount,
        ];
    }

    private function syncReadyToShipOrderWindow(
        string $since,
        string $to,
        array $headers,
        string $endpointList,
        string $endpointBatch,
        string $accessKey,
        string $secretKey,
        string $country,
        string $host,
        bool $yieldToHotSync = false
    ): array {
        $totalProcessed = 0;
        $newCount = 0;
        $updatedCount = 0;

        $this->syncOrderByCursor([
            'orderStatus' => 'READY_TO_SHIP',
            'lastUpdateSince' => $since,
            'lastUpdateTo' => $to,
        ], $headers, $endpointList, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed, $yieldToHotSync);

        return [
            'totalProcessed' => $totalProcessed,
            'new' => $newCount,
            'updated' => $updatedCount,
        ];
    }


    public function syncFirstTime(): array
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');

        extract($this->getApiContext());

        $totalProcessed = 0;
        $newCount = 0;
        $updatedCount = 0;

        $statuses = [
            'PAID',
            'READY_TO_SHIP',
            'SHIPPING',
            'DELIVERED',
            'CANCELLED',
            'RETURNED',
            'COMPLETED',
        ];


        for ($i = 20; $i >= 0; $i--) {

            $since = now()->subDays($i + 1)->utc()->format('Y-m-d\TH:i:s\Z');
            $to = now()->subDays($i)->utc()->format('Y-m-d\TH:i:s\Z');

            foreach ($statuses as $status) {

                $nextCursor = null;
                $page = 1;

                do {
                    $bodyList = [
                        'createSince' => $since,
                        'createTo' => $to,
                        'orderStatus' => $status,
                        'size' => 100,
                    ];

                    if ($nextCursor) {
                        $bodyList['nextCursor'] = $nextCursor;
                    }

                    $listResponse = Http::timeout(90)
                        ->withHeaders($headers)
                        ->post($host . $endpointList, $bodyList);

                    $responseData = $listResponse->json();
                    $listData = $responseData['data']['content'] ?? [];
                    $hasMore = $responseData['data']['more'] ?? false;
                    $nextCursor = $responseData['data']['nextCursor'] ?? null;

                    dump("[FIRST SYNC] {$since} -> {$to} | {$status} | Page {$page} | dapat " . count($listData));

                    if (!empty($listData)) {
                        $this->saveOrderBatch($listData, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed);
                    }

                    $page++;
                    sleep(1);

                } while ($hasMore);
            }
        }

        SyncLog::updateOrCreate(
            ['type' => 'orders'],
            ['last_sync_at' => now()]
        );

        return [
            'totalProcessed' => $totalProcessed,
            'new' => $newCount,
            'updated' => $updatedCount,
        ];
    }




    private function saveOrderBatch(
        $listData,
        $endpointBatch,
        $accessKey,
        $secretKey,
        $country,
        $host,
        &$newCount,
        &$updatedCount,
        &$totalProcessed,
        bool $yieldToHotSync = false
    )
    {
        // ambil orderId
        $orderIds = collect($listData)->pluck('orderId')->filter()->unique()->values()->toArray();
        $chunks = array_chunk($orderIds, 20);

        foreach ($chunks as $chunk) {
            if ($yieldToHotSync && $this->isHotSyncRunning()) {
                break;
            }

            $signatureBatch = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointBatch, $secretKey));
            $headersBatch = [
                'Content-Type' => 'application/json',
                'X-Advai-Country' => $country,
                'Authorization' => $accessKey . ':' . $signatureBatch
            ];

            $bodyBatch = [
                'orderIds' => $chunk,
                'historicalData' => false
            ];

            $batchResponse = $this->postToGinee($host, $endpointBatch, $headersBatch, $bodyBatch);
            $batchData = $batchResponse['data'] ?? [];

            foreach ($batchData as $order) {
                if (($order['externalOrderSn'] ?? null) === '260211E11Y49WK') {
                    dump('===== CEK PRINT INFO =====');
                    dump($order['printInfo'] ?? null);
                    dump($order['printInfoList'] ?? null);
                }

                $trackingNumber = $this->normalizeTrackingNumber(
                    $order['trackingNumber']
                    ?? ($order['fulfillmentInfo']['trackingNumber'] ?? null)
                    ?? ($order['fulfillmentInfoList'][0]['trackingNumber'] ?? null)
                    ?? ($order['logisticsInfos'][0]['logisticsTrackingNumber'] ?? null)
                    ?? ($order['logisticInfoList'][0]['trackingNumber'] ?? null)
                );

                $skuList = !empty($order['items'])
                    ? collect($order['items'])->pluck('sku')->filter()->unique()->implode(',')
                    : null;

                // 🔥 ambil info print
                $labelStatus = $order['printInfo']['labelPrintStatus'] ?? 'NOT_PRINTED';
                $labelTime = isset($order['printInfo']['labelPrintTime'])
                    ? Carbon::parse($order['printInfo']['labelPrintTime'])->format('Y-m-d H:i:s')
                    : null;

                if (!$this->shouldPersistOrder($order['orderStatus'] ?? null, $labelStatus)) {
                    continue;
                }

                $updateData = [
                    'platform' => $order['channel'] ?? null,
                    'customer_name' => $order['customerInfo']['name'] ?? null,
                    'customer_phone' => $order['customerInfo']['mobile'] ?? null,
                    'total_amount' => $order['paymentInfo']['totalAmount'] ?? 0,
                    'status' => $order['orderStatus'] ?? null,
                    'order_date' => isset($order['createAt']) ? Carbon::parse($order['createAt'])->format('Y-m-d H:i:s') : null,
                    'total_qty' => $order['totalQuantity'] ?? (isset($order['items']) ? collect($order['items'])->sum('quantity') : 0),
                    'sku' => $skuList,

                    // 🔥 ini yang paling penting
                    'label_print_status' => $labelStatus,
                    'label_print_time' => $labelTime,

                ];

                if (!empty($trackingNumber)) {
                    $updateData['tracking_number'] = $trackingNumber;
                }

                // ✅ Cari order existing
                $orderModel = null;

                if (!empty($trackingNumber)) {
                    $orderModel = Order::where('tracking_number', $trackingNumber)->first();

                    if (!$orderModel) {
                        $orderModel = Order::whereNotNull('tracking_number')
                            ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
                            ->first();
                    }
                }

                $orderNumber = $this->normalizeNullableString($order['externalOrderSn'] ?? null);
                if (!$orderModel && !empty($orderNumber)) {
                    $orderModel = Order::where('order_number', $orderNumber)->first();

                    if (!$orderModel) {
                        $orderModel = Order::whereRaw('TRIM(order_number) = ?', [$orderNumber])->first();
                    }
                }

                // ✅ Insert / Update
                if ($orderModel) {
                    $orderModel->update($updateData);
                    $updatedCount++;
                } else {
                    $updateData['order_number'] = $orderNumber;
                    $orderModel = Order::create($updateData);
                    $newCount++;
                }

                $totalProcessed++;

                // ✅ Save Items
                if (!empty($order['items'])) {
                    foreach ($order['items'] as $item) {

                        $sku = $item['sku'] ?? $item['itemId'] ?? 'NO-SKU';

                        OrderItem::updateOrCreate(
                            [
                                'order_id' => $orderModel->id,
                                'sku' => $sku,
                            ],
                            [
                                'product_name' => $item['productName'] ?? null,
                                'quantity' => $item['quantity'] ?? 0,
                                'price' => $item['price'] ?? 0,
                                'image' => $item['productImageUrl'] ?? null,
                            ]
                        );
                    }
                }

                $this->reconcileNoDataGineeLogs($orderModel, $trackingNumber);
            }

            sleep(1); // biar ga kena rate limit
        }
    }

    private function normalizeTrackingNumber($trackingNumber): ?string
    {
        return $this->normalizeNullableString($trackingNumber);
    }

    private function shouldPersistOrder(?string $orderStatus, ?string $labelStatus): bool
    {
        $normalizedOrderStatus = strtoupper(trim((string) $orderStatus));
        $normalizedLabelStatus = strtoupper(trim((string) $labelStatus));

        return $normalizedOrderStatus === 'READY_TO_SHIP'
            || $normalizedLabelStatus === 'PRINTED';
    }

    private function postToGinee(string $host, string $endpoint, array $headers, array $body): array
    {
        $response = Http::timeout(90)
            ->withHeaders($headers)
            ->post($host . $endpoint, $body);

        if ($response->failed()) {
            Log::error('Request ke Ginee gagal', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $body,
                'response_excerpt' => substr($response->body(), 0, 1000),
            ]);

            $response->throw();
        }

        $json = $response->json();

        if (!is_array($json) || !array_key_exists('data', $json)) {
            Log::error('Response Ginee tidak valid', [
                'endpoint' => $endpoint,
                'body' => $body,
                'response_excerpt' => substr($response->body(), 0, 1000),
            ]);

            throw new \RuntimeException("Response dari {$endpoint} tidak valid.");
        }

        return $json;
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function reconcileNoDataGineeLogs(Order $orderModel, ?string $trackingNumber): void
    {
        if (empty($trackingNumber)) {
            return;
        }

        NoDataGineeLog::query()
            ->whereNull('order_id')
            ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
            ->update(['order_id' => $orderModel->id]);

        $this->noDataGineeSerialPackingService->reconcilePendingLogsForOrder(
            $orderModel,
            $trackingNumber
        );
    }
}






