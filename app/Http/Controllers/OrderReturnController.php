<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturnLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderReturnController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string|max:255',
        ], [
            'tracking_number.required' => 'Tracking number tidak boleh kosong',
            'tracking_number.string' => 'Tracking number harus berupa teks',
            'tracking_number.max' => 'Tracking number maksimal 255 karakter',
        ]);

        $trackingNumber = $this->normalizeTrackingNumber($validated['tracking_number']);

        if ($trackingNumber === '') {
            return response()->json([
                'message' => 'Tracking number tidak boleh kosong',
            ], 422);
        }

        $order = $this->findOrderByTracking($trackingNumber, ['items']);

        if (!$order) {
            return response()->json([
                'message' => 'Order tidak ditemukan',
            ], 404);
        }

        $storedTrackingNumber = $this->normalizeTrackingNumber(
            $order->tracking_number ?: $trackingNumber
        );

        if ($this->hasExistingReturnLog($storedTrackingNumber)) {
            return $this->duplicateReturnResponse($storedTrackingNumber);
        }

        $scanResult = DB::transaction(function () use ($order, $storedTrackingNumber) {
            Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->hasExistingReturnLog($storedTrackingNumber)) {
                return [
                    'is_duplicate' => true,
                    'return_log' => null,
                ];
            }

            $returnLog = OrderReturnLog::create([
                'order_id' => $order->id,
                'tracking_number' => $storedTrackingNumber,
                'performed_by' => Auth::user()->name ?? 'System',
                'notes' => 'Order return berhasil discan',
            ]);

            return [
                'is_duplicate' => false,
                'return_log' => $returnLog,
            ];
        });

        if ($scanResult['is_duplicate']) {
            return $this->duplicateReturnResponse($storedTrackingNumber);
        }

        $returnLog = $scanResult['return_log'];
        $returnLog->load(['order.items']);

        return response()->json([
            'message' => 'Return berhasil dicatat',
            'data' => [
                'log' => $this->formatReturnLog($returnLog),
                'order' => $this->formatOrder($returnLog->order),
            ],
        ], 201);
    }

    private function findOrderByTracking(string $trackingNumber, array $relations = [])
    {
        $normalizedTrackingNumber = $this->normalizeTrackingNumber($trackingNumber);

        if ($normalizedTrackingNumber === '') {
            return null;
        }

        $query = Order::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $order = (clone $query)
            ->where('tracking_number', $normalizedTrackingNumber)
            ->first();

        if ($order) {
            return $order;
        }

        return (clone $query)
            ->whereNotNull('tracking_number')
            ->whereRaw('TRIM(tracking_number) = ?', [$normalizedTrackingNumber])
            ->first();
    }

    private function normalizeTrackingNumber($trackingNumber): string
    {
        return trim(urldecode((string) $trackingNumber));
    }

    private function hasExistingReturnLog(string $trackingNumber): bool
    {
        return OrderReturnLog::query()
            ->where('tracking_number', $trackingNumber)
            ->orWhereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
            ->exists();
    }

    private function duplicateReturnResponse(string $trackingNumber)
    {
        return response()->json([
            'message' => "Tracking number {$trackingNumber} sudah pernah discan return dan tidak bisa discan ulang.",
        ], 409);
    }

    private function formatReturnLog(OrderReturnLog $returnLog): array
    {
        return [
            'id' => $returnLog->id,
            'tracking_number' => $returnLog->tracking_number,
            'performed_by' => $returnLog->performed_by,
            'notes' => $returnLog->notes,
            'created_at' => optional($returnLog->created_at)->toDateTimeString(),
        ];
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'status' => $order->status,
            'total_qty' => (int) ($order->total_qty ?: $order->items->sum('quantity')),
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'image' => $item->image,
                ];
            })->values(),
        ];
    }
}
