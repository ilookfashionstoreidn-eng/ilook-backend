<?php


namespace App\Services;


use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Helpers\GineeSignature;
use App\Models\SyncLog;


class GineeOrderService
{

public function syncRecentOrders(): array
{
    ini_set('max_execution_time', 0);
    ini_set('memory_limit', '2048M');

    $accessKey = env('GINEE_ACCESS_KEY');
    $secretKey = env('GINEE_SECRET_KEY');
    $country   = env('GINEE_COUNTRY', 'ID');
    $host      = env('GINEE_API_HOST', 'https://api.ginee.com');

    $endpointList  = '/openapi/order/v2/list-order';
    $endpointBatch = '/openapi/order/v1/batch-get';

    $signatureList = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointList, $secretKey));

    $headers = [
        'Content-Type' => 'application/json',
        'X-Advai-Country' => $country,
        'Authorization' => $accessKey . ':' . $signatureList
    ];

    $syncLog = SyncLog::firstOrCreate(
        ['type' => 'orders'],
        ['last_sync_at' => now()->subHours(12)]
    );

    $since = Carbon::parse($syncLog->last_sync_at)
        ->subHours(3) // buffer anti miss
        ->utc()
        ->format('Y-m-d\TH:i:s\Z');

    $to = now()->utc()->format('Y-m-d\TH:i:s\Z');

    $totalProcessed = 0;
    $newCount = 0;
    $updatedCount = 0;

    /*
    =================================
    1. UPDATE ORDER STATUS
    =================================
    */

    $this->syncOrderByCursor([
        'lastUpdateSince' => $since,
        'lastUpdateTo'    => $to
    ], $headers, $endpointList, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed);

    /*
    =================================
    2. ORDER BARU
    =================================
    */

    $this->syncOrderByCursor([
        'createSince' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'),
        'createTo'    => $to
    ], $headers, $endpointList, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed);

    /*
    =================================
    3. REPAIR SYNC (ANTI MISS)
    =================================
    */

    $this->syncOrderByCursor([
        'createSince' => now()->subDays(2)->utc()->format('Y-m-d\TH:i:s\Z'),
        'createTo'    => $to
    ], $headers, $endpointList, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed);

    $syncLog->update([
        'last_sync_at' => now()
    ]);

    return [
        'totalProcessed' => $totalProcessed,
        'new' => $newCount,
        'updated' => $updatedCount,
    ];
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
    &$totalProcessed
) {

    $nextCursor = null;

    do {

        $body = $params + [
            'size' => 100
        ];

        if ($nextCursor) {
            $body['nextCursor'] = $nextCursor;
        }

        $response = Http::timeout(90)
            ->withHeaders($headers)
            ->post($host . $endpointList, $body)
            ->json();

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
                $totalProcessed
            );

        }

        usleep(200000); // anti rate limit

    } while ($hasMore);
}


    public function syncFirstTime(): array
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');

        $accessKey = env('GINEE_ACCESS_KEY');
        $secretKey = env('GINEE_SECRET_KEY');
        $country   = env('GINEE_COUNTRY', 'ID');
        $host      = env('GINEE_API_HOST', 'https://api.ginee.com');

        $endpointList  = '/openapi/order/v2/list-order';
        $endpointBatch = '/openapi/order/v1/batch-get';

        $signatureList = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointList, $secretKey));
        $headersList = [
            'Content-Type' => 'application/json',
            'X-Advai-Country' => $country,
            'Authorization' => $accessKey . ':' . $signatureList
        ];

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
           $to    = now()->subDays($i)->utc()->format('Y-m-d\TH:i:s\Z');

            foreach ($statuses as $status) {

                $nextCursor = null;
                $page = 1;

                do {
                    $bodyList = [
                        'createSince' => $since,
                        'createTo'    => $to,
                        'orderStatus'     => $status,
                        'size'            => 100,
                    ];

                    if ($nextCursor) {
                        $bodyList['nextCursor'] = $nextCursor;
                    }

                    $listResponse = Http::timeout(90)
                        ->withHeaders($headersList)
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


    

    private function saveOrderBatch($listData, $endpointBatch, $accessKey, $secretKey, $country, $host, &$newCount, &$updatedCount, &$totalProcessed)
    {
        // ambil orderId
        $orderIds = collect($listData)->pluck('orderId')->filter()->unique()->values()->toArray();
        $chunks = array_chunk($orderIds, 20);

        foreach ($chunks as $chunk) {
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

            $batchResponse = Http::timeout(90)
                ->withHeaders($headersBatch)
                ->post($host . $endpointBatch, $bodyBatch);

            $batchData = $batchResponse->json()['data'] ?? [];

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
                $labelTime   = isset($order['printInfo']['labelPrintTime']) 
                                ? Carbon::parse($order['printInfo']['labelPrintTime'])->format('Y-m-d H:i:s') 
                                : null;

                $updateData = [
                    'platform'        => $order['channel'] ?? null,
                    'customer_name'   => $order['customerInfo']['name'] ?? null,
                    'customer_phone'  => $order['customerInfo']['mobile'] ?? null,
                    'total_amount'    => $order['paymentInfo']['totalAmount'] ?? 0,
                    'status'          => $order['orderStatus'] ?? null,
                    'order_date'      => isset($order['createAt']) ? Carbon::parse($order['createAt'])->format('Y-m-d H:i:s') : null,
                    'total_qty'       => $order['totalQuantity'] ?? (isset($order['items']) ? collect($order['items'])->sum('quantity') : 0),
                    'sku'             => $skuList,

                    // 🔥 ini yang paling penting
                   'label_print_status' => $labelStatus,
                    'label_print_time'   => $labelTime,

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
                                'sku'      => $sku,
                            ],
                            [
                                'product_name' => $item['productName'] ?? null,
                                'quantity'     => $item['quantity'] ?? 0,
                                'price'        => $item['price'] ?? 0,
                                'image'        => $item['productImageUrl'] ?? null,
                            ]
                        );
                    }
                }
            }

            sleep(1); // biar ga kena rate limit
        }
    }

    private function normalizeTrackingNumber($trackingNumber): ?string
    {
        return $this->normalizeNullableString($trackingNumber);
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






