<?php

namespace App\Services;

use App\Helpers\GineeSignature;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\NoDataGineeLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * On-demand fetch service: when a tracking number is not found in the local DB,
 * this service pulls recent orders from Ginee using progressive time windows,
 * persists them, and returns the matching order (if any).
 *
 * Progressive windows:
 *   Step 1: last 3 days (fast, covers most fresh orders)
 *   Step 2: last 14 days
 *   Step 3: last 45 days
 *   Step 4: last 90 days (Ginee API maximum)
 *
 * Stops as soon as the order is found, minimizing API calls.
 */
class GineeOnDemandFetchService
{
    /** @var int Cache lock TTL in seconds – prevents parallel fetches for the same tracking */
    private const LOCK_TTL_SECONDS = 120;

    /** Progressive lookback windows in days – tries each until order is found */
    private const LOOKBACK_WINDOWS_DAYS = [3, 14, 45, 90];

    /**
     * Try to find the order by tracking number in the local DB.
     * If not found, progressively fetch from Ginee with expanding time windows.
     */
    public function findOrFetchByTracking(string $trackingNumber, array $relations = []): ?Order
    {
        $normalized = $this->normalizeTrackingNumber($trackingNumber);

        if ($normalized === '') {
            return null;
        }

        // 1. Quick local lookup
        $order = $this->findInLocalDb($normalized, $relations);
        if ($order) {
            return $order;
        }

        // 2. Acquire a lock to prevent stampede fetches for the same tracking
        $lockKey = 'ginee-ondemand:' . md5($normalized);

        if (!Cache::lock($lockKey, self::LOCK_TTL_SECONDS)->get()) {
            // Another request is already fetching – wait briefly and retry local DB
            sleep(5);
            return $this->findInLocalDb($normalized, $relations);
        }

        try {
            // 3. Progressive fetch: try small window first, expand only if needed
            foreach (self::LOOKBACK_WINDOWS_DAYS as $days) {
                Log::info("[GineeOnDemand] Trying {$days}-day window for tracking: {$normalized}");

                $fetchedCount = $this->fetchAndPersistWindow($days);

                Log::info("[GineeOnDemand] Fetched {$fetchedCount} orders from {$days}-day window");

                // Check if we found the order now
                $order = $this->findInLocalDb($normalized, $relations);
                if ($order) {
                    Log::info("[GineeOnDemand] Order found after {$days}-day window fetch");
                    return $order;
                }
            }

            // After all windows exhausted, still not found
            Log::info("[GineeOnDemand] Order not found after all windows for tracking: {$normalized}");
            return null;
        } catch (Throwable $e) {
            Log::warning('[GineeOnDemand] Failed to fetch from Ginee', [
                'tracking_number' => $normalized,
                'error' => $e->getMessage(),
            ]);
            return null;
        } finally {
            Cache::lock($lockKey)->forceRelease();
        }
    }

    /**
     * Fetch orders from a specific time window and persist to DB.
     * Uses both lastUpdate AND createDate sweeps to catch all orders.
     */
    private function fetchAndPersistWindow(int $days): int
    {
        $ctx = $this->getApiContext();

        $to = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $since = now()->subDays($days)->utc()->format('Y-m-d\TH:i:s\Z');

        $totalFetched = 0;

        // Sweep 1: by lastUpdate (catches orders that were recently modified)
        $totalFetched += $this->syncCursorWindow(
            ['lastUpdateSince' => $since, 'lastUpdateTo' => $to],
            $ctx,
            3 // max pages for on-demand to keep it fast
        );

        // Sweep 2: by createDate (catches orders created in this window)
        $totalFetched += $this->syncCursorWindow(
            ['createSince' => $since, 'createTo' => $to],
            $ctx,
            3
        );

        return $totalFetched;
    }

    /**
     * Fetch paginated orders from Ginee list API and persist via batch-get.
     */
    private function syncCursorWindow(array $timeParams, array $ctx, int $maxPages = 3): int
    {
        $nextCursor = null;
        $totalPersisted = 0;
        $page = 0;

        do {
            $body = $timeParams + ['size' => 100];

            if ($nextCursor) {
                $body['nextCursor'] = $nextCursor;
            }

            $signatureList = str_replace(
                ["\r", "\n"],
                '',
                GineeSignature::generate('POST', $ctx['endpointList'], $ctx['secretKey'])
            );

            $headersList = [
                'Content-Type' => 'application/json',
                'X-Advai-Country' => $ctx['country'],
                'Authorization' => $ctx['accessKey'] . ':' . $signatureList,
            ];

            $response = Http::timeout(30)
                ->withHeaders($headersList)
                ->post($ctx['host'] . $ctx['endpointList'], $body)
                ->json();

            $listData = $response['data']['content'] ?? [];
            $hasMore = $response['data']['more'] ?? false;
            $nextCursor = $response['data']['nextCursor'] ?? null;

            if (!empty($listData)) {
                $totalPersisted += $this->persistBatch($listData, $ctx);
            }

            $page++;
            usleep(300000); // 300ms delay between pages

        } while ($hasMore && $page < $maxPages);

        return $totalPersisted;
    }

    /**
     * Get batch details from Ginee and persist to DB.
     * Mirrors the logic in GineeOrderService::saveOrderBatch().
     */
    private function persistBatch(array $listData, array $ctx): int
    {
        $orderIds = collect($listData)->pluck('orderId')->filter()->unique()->values()->toArray();
        $chunks = array_chunk($orderIds, 20);
        $persisted = 0;

        foreach ($chunks as $chunk) {
            $signatureBatch = str_replace(
                ["\r", "\n"],
                '',
                GineeSignature::generate('POST', $ctx['endpointBatch'], $ctx['secretKey'])
            );

            $headersBatch = [
                'Content-Type' => 'application/json',
                'X-Advai-Country' => $ctx['country'],
                'Authorization' => $ctx['accessKey'] . ':' . $signatureBatch,
            ];

            $batchResponse = Http::timeout(30)
                ->withHeaders($headersBatch)
                ->post($ctx['host'] . $ctx['endpointBatch'], [
                    'orderIds' => $chunk,
                    'historicalData' => false,
                ])
                ->json();

            $batchData = $batchResponse['data'] ?? [];

            foreach ($batchData as $order) {
                try {
                    $trackingNumber = $this->normalizeNullableString(
                        $order['trackingNumber']
                        ?? ($order['fulfillmentInfo']['trackingNumber'] ?? null)
                        ?? ($order['fulfillmentInfoList'][0]['trackingNumber'] ?? null)
                        ?? ($order['logisticsInfos'][0]['logisticsTrackingNumber'] ?? null)
                        ?? ($order['logisticInfoList'][0]['trackingNumber'] ?? null)
                    );

                    $skuList = !empty($order['items'])
                        ? collect($order['items'])->pluck('sku')->filter()->unique()->implode(',')
                        : null;

                    $labelStatus = $order['printInfo']['labelPrintStatus'] ?? 'NOT_PRINTED';
                    $labelTime = isset($order['printInfo']['labelPrintTime'])
                        ? Carbon::parse($order['printInfo']['labelPrintTime'])->format('Y-m-d H:i:s')
                        : null;

                    $updateData = [
                        'platform' => $order['channel'] ?? null,
                        'customer_name' => $order['customerInfo']['name'] ?? null,
                        'customer_phone' => $order['customerInfo']['mobile'] ?? null,
                        'total_amount' => $order['paymentInfo']['totalAmount'] ?? 0,
                        'status' => $order['orderStatus'] ?? null,
                        'order_date' => isset($order['createAt']) ? Carbon::parse($order['createAt'])->format('Y-m-d H:i:s') : null,
                        'total_qty' => $order['totalQuantity'] ?? (isset($order['items']) ? collect($order['items'])->sum('quantity') : 0),
                        'sku' => $skuList,
                        'label_print_status' => $labelStatus,
                        'label_print_time' => $labelTime,
                    ];

                    if (!empty($trackingNumber)) {
                        $updateData['tracking_number'] = $trackingNumber;
                    }

                    // Find existing order
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

                    // Insert or Update
                    if ($orderModel) {
                        $orderModel->update($updateData);
                    } else {
                        $updateData['order_number'] = $orderNumber;
                        $orderModel = Order::create($updateData);
                    }

                    // Reconcile NoDataGineeLogs
                    $this->reconcileNoDataGineeLogs($orderModel, $trackingNumber);

                    // Save Items
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

                    $persisted++;
                } catch (Throwable $e) {
                    Log::warning('[GineeOnDemand] Failed to persist order', [
                        'order_number' => $order['externalOrderSn'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            usleep(500000); // 500ms delay between batch chunks
        }

        return $persisted;
    }

    private function getApiContext(): array
    {
        return [
            'accessKey' => env('GINEE_ACCESS_KEY'),
            'secretKey' => env('GINEE_SECRET_KEY'),
            'country' => env('GINEE_COUNTRY', 'ID'),
            'host' => env('GINEE_API_HOST', 'https://api.ginee.com'),
            'endpointList' => '/openapi/order/v2/list-order',
            'endpointBatch' => '/openapi/order/v1/batch-get',
        ];
    }

    private function findInLocalDb(string $trackingNumber, array $relations = []): ?Order
    {
        $query = Order::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $order = (clone $query)->where('tracking_number', $trackingNumber)->first();

        if ($order) {
            return $order;
        }

        return (clone $query)
            ->whereNotNull('tracking_number')
            ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
            ->first();
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
    }

    private function normalizeTrackingNumber($value): string
    {
        return trim(urldecode((string) ($value ?? '')));
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
