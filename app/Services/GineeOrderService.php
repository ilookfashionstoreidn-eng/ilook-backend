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

        $syncLog = SyncLog::firstOrCreate(['type' => 'orders'], [
            'last_sync_at' => now()->subDay()
        ]);

        $since = $syncLog->last_sync_at->subHours(2)->toIso8601String(); 
        $to = now()->toIso8601String();

        $totalProcessed = 0;
        $newCount = 0;
        $updatedCount = 0;

        $nextCursor = null;
        $page = 1;

        $statuses = [
            'PAID',
            'READY_TO_SHIP',
            'SHIPPING',
            'DELIVERED',
            'CANCELLED',
            'RETURNED',
        ];

        
        foreach ($statuses as $status) {

            $nextCursor = null;
            $page = 1;
            do {
                $bodyList = [
                    'lastUpdateSince' => $since,
                    'lastUpdateTo'    => $to,
                    'orderStatus'     => $status, // ✅ satu status per request
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

                dump("Tanggal {$since} s/d {$to} | Page {$page} -> dapat " . count($listData) . " order | more: " . ($hasMore ? 'yes' : 'no'));
                dump("Status {$status} | Page {$page} -> dapat " . count($listData) . " order");
            
                if (!empty($listData)) {
                    $this->saveOrderBatch($listData, $endpointBatch, $accessKey, $secretKey, $country, $host, $newCount, $updatedCount, $totalProcessed);
                }

                $page++;
                sleep(1);
            } while ($hasMore);
        }

        // Update waktu terakhir sync ke sekarang
        $syncLog->update(['last_sync_at' => now()]);

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


        for ($i = 8; $i >= 0; $i--) {

            $since = now()->subDays($i + 1)->toIso8601String();
            $to    = now()->subDays($i)->toIso8601String();

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


            $batchResponse = Http::timeout(90)->withHeaders($headersBatch)->post($host . $endpointBatch, $bodyBatch);
            $batchData = $batchResponse->json()['data'] ?? [];


            foreach ($batchData as $order) {
                $trackingNumber =
                    $order['trackingNumber']
                    ?? ($order['fulfillmentInfo']['trackingNumber'] ?? null)
                    ?? ($order['fulfillmentInfoList'][0]['trackingNumber'] ?? null)
                    ?? ($order['logisticsInfos'][0]['logisticsTrackingNumber'] ?? null)
                    ?? ($order['logisticInfoList'][0]['trackingNumber'] ?? null);

                 $skuList = !empty($order['items'])
                    ? collect($order['items'])->pluck('sku')->filter()->unique()->implode(',')
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
                ];


                if (!empty($trackingNumber)) {
                    $updateData['tracking_number'] = $trackingNumber;
                }

                // ✅ Cari order existing dengan 2 step:
                $orderModel = null;

                // 1️⃣ Cek berdasarkan tracking number dulu
                if (!empty($trackingNumber)) {
                    $orderModel = Order::where('tracking_number', $trackingNumber)->first();
                }

                // 2️⃣ Kalau belum ketemu → cek berdasarkan order_number
                $orderNumber = $order['externalOrderSn'] ?? null;
                if (!$orderModel && !empty($orderNumber)) {
                    $orderModel = Order::where('order_number', $orderNumber)->first();
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
                        OrderItem::updateOrCreate(
                            [
                                'order_id' => $orderModel->id,
                                'sku'      => $item['sku'] ?? null,
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
    }






