<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\GineeSignature;
use Illuminate\Support\Facades\Http;
use App\Services\GineeOrderService;

class GineeSyncController extends Controller
{
    public function __construct(private GineeOrderService $gineeOrderService)
    {
    }

    public function listOrders(Request $request)
    {
        ini_set('max_execution_time', 300);

        $accessKey = env('GINEE_ACCESS_KEY');
        $secretKey = env('GINEE_SECRET_KEY');
        $country = env('GINEE_COUNTRY', 'ID');
        $host = env('GINEE_API_HOST', 'https://api.ginee.com');
        $endpointList = '/openapi/order/v2/list-order';
        $endpointDetail = '/openapi/order/v2/get-order-detail';

        $signature = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointList, $secretKey));

        $headers = [
            'Content-Type' => 'application/json',
            'X-Advai-Country' => $country,
            'Authorization' => $accessKey . ':' . $signature
        ];

        $lastUpdateSince = now()->subHours(3)->toIso8601String();
        $lastUpdateTo = now()->toIso8601String();

     
       $body = [
            'lastUpdateSince' => $lastUpdateSince,
            'lastUpdateTo' => $lastUpdateTo,
            'orderStatus' => $request->orderStatus ?? null,
            'pageSize' => $request->pageSize ?? 15,
            'page' => $request->page ?? 1,
        ];


        $response = Http::timeout(60)->withHeaders($headers)->post($host . $endpointList, $body);
        $responseData = $response->json();

        $orders = array_slice($responseData['data']['content'] ?? [], 0, 3); // Batasi 3 untuk testing

        
        foreach ($orders as &$order) {

           
            $signatureDetail = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointDetail, $secretKey));
            $headersDetail = [
                'Content-Type' => 'application/json',
                'X-Advai-Country' => $country,
                'Authorization' => $accessKey . ':' . $signatureDetail
            ];

            $detailRes = Http::timeout(30)->withHeaders($headersDetail)
                ->post($host . $endpointDetail, ['orderId' => $order['orderId']]);

            $detailJson = $detailRes->json();
            $detailData = $detailJson['data'] ?? [];

           
            $order['customer_phone'] = $detailData['shippingAddress']['phone'] ?? null;
            $order['tracking_number'] = $detailData['logisticInfoList'][0]['trackingNumber'] ?? null;
            $order['items'] = $detailData['orderItemList'] ?? [];

          
            $order['raw_detail'] = $detailJson;
        }

        return response()->json($orders);
    }

    public function orderDetails(Request $request)
    {
        $orderIds = $request->input('orderIds', []);

        if (empty($orderIds)) {
            return response()->json(['message' => 'orderIds tidak boleh kosong'], 422);
        }

        $accessKey = env('GINEE_ACCESS_KEY');
        $secretKey = env('GINEE_SECRET_KEY');
        $country   = env('GINEE_COUNTRY', 'ID');
        $host      = env('GINEE_API_HOST', 'https://api.ginee.com');

        $endpoint = '/openapi/order/v1/batch-get';
        $signature = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpoint, $secretKey));

        $headers = [
            'Content-Type' => 'application/json',
            'X-Advai-Country' => $country,
            'Authorization' => $accessKey . ':' . $signature
        ];

        $body = [
            'orderIds' => $orderIds,
            'historicalData' => false
        ];

        $response = Http::timeout(60)->withHeaders($headers)->post($host . $endpoint, $body);
        $json = $response->json();

        if (!isset($json['data']) || !is_array($json['data'])) {
            return response()->json([
                'code' => 'ERROR',
                'message' => $json['message'] ?? 'Gagal mengambil data order',
                'data' => []
            ], $response->status());
        }

        $orders = collect($json['data'])->map(function ($order) {
            return [
                'order_number'    => $order['externalOrderSn'] ?? null,
                'tracking_number' => $order['logisticsInfos'][0]['logisticsTrackingNumber'] ?? null,
                'platform'        => $order['channel'] ?? null,
                'customer_name'   => $order['customerInfo']['name'] ?? null,
                'customer_phone'  => $order['customerInfo']['mobile'] ?? null,
                'total_amount'    => $order['paymentInfo']['totalAmount'] ?? 0,
                'status'          => $order['orderStatus'] ?? null,
                'order_date'      => $order['createAt'] ?? null,
                'items'           => collect($order['items'] ?? [])->map(function ($item) use ($order) {
                    return [
                        'order_id'     => $order['orderId'] ?? null,
                        'sku'          => $item['sku'] ?? null,
                        'product_name' => $item['productName'] ?? null,
                        'quantity'     => $item['quantity'] ?? 0,
                        'price'        => $item['actualPrice'] ?? 0,
                        'actual_total_price'=> $item['actualTotalPrice'] ?? null,
                        'image'          => $item['productImageUrl'] ?? null,
                    ];
                })->toArray(),
            ];
        });

        return response()->json([
            'code'    => 'SUCCESS',
            'message' => 'OK',
            'data'    => $orders,
        ]);
    }


    public function syncRecentOrders()
    {
        $result = $this->gineeOrderService->syncRecentOrders();

        return response()->json([
            'message' => 'Sinkronisasi order terbaru selesai',
            'totalProcessed' => $result['totalProcessed'],
            'new' => $result['new'],
            'updated' => $result['updated'],
        ]);
    }

    
}


    
