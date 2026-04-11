<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderLog;
use App\Services\GineeOnDemandFetchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackingBelumBarcodeController extends Controller
{
    public function showByTracking($trackingNumber)
    {
        $normalized = trim(urldecode((string) $trackingNumber));
        $order = $this->findOrderByTracking($normalized);

        // On-demand fetch: jika order belum ada di DB, coba ambil dari Ginee API
        if (!$order) {
            $order = app(GineeOnDemandFetchService::class)
                ->findOrFetchByTracking($normalized, ['items']);
        }

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if ($order->is_packed) {
            return response()->json([
                'message' => 'Order ini sudah berstatus packed dan tidak bisa discan ulang.',
            ], 409);
        }

        if ($this->hasBelumBarcodeLog($order->id)) {
            return response()->json([
                'message' => 'Order ini sudah pernah disubmit melalui packing belum barcode dan siap dilanjutkan ke proses packing.',
            ], 409);
        }

        return response()->json($this->formatOrderPreview($order));
    }

    public function submit(Request $request)
    {
        try {
            $request->validate([
                'scanner_name' => 'required|string|min:1|max:255',
                'tracking_numbers' => 'required|array|min:1|max:1000',
                'tracking_numbers.*' => 'required|string|min:1|max:255',
            ], [
                'scanner_name.required' => 'Nama scanner wajib diisi',
                'scanner_name.string' => 'Nama scanner harus berupa teks',
                'tracking_numbers.required' => 'Daftar tracking number wajib diisi',
                'tracking_numbers.array' => 'Daftar tracking number harus berupa array',
                'tracking_numbers.min' => 'Minimal harus ada 1 tracking number',
                'tracking_numbers.max' => 'Maksimal 1000 tracking number per request',
                'tracking_numbers.*.required' => 'Tracking number tidak boleh kosong',
                'tracking_numbers.*.string' => 'Tracking number harus berupa teks',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors(),
            ], 422);
        }

        $scannerName = trim((string) $request->input('scanner_name'));
        $trackingNumbers = collect($request->input('tracking_numbers', []))
            ->map(fn ($trackingNumber) => $this->normalizeTrackingNumber($trackingNumber))
            ->values();

        if ($trackingNumbers->contains(fn ($trackingNumber) => $trackingNumber === '')) {
            return response()->json([
                'message' => 'Tracking number tidak boleh kosong',
            ], 422);
        }

        $trackingCounts = $trackingNumbers->countBy();
        $duplicateValue = $trackingCounts->search(fn ($count) => $count > 1);

        if ($duplicateValue !== false) {

            return response()->json([
                'message' => "Tracking number {$duplicateValue} terdeteksi duplikat dalam sesi ini",
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($trackingNumbers, $scannerName) {
                $orders = [];

                foreach ($trackingNumbers as $trackingNumber) {
                    $order = $this->findOrderByTrackingForUpdate($trackingNumber);

                    // On-demand fetch: jika order belum di DB, coba ambil dari Ginee API
                    if (!$order) {
                        $fetched = app(GineeOnDemandFetchService::class)
                            ->findOrFetchByTracking($trackingNumber, ['items']);

                        if ($fetched) {
                            // Re-acquire with lock setelah data dipersist
                            $order = $this->findOrderByTrackingForUpdate($trackingNumber);
                        }
                    }

                    if (!$order) {
                        throw new \RuntimeException("Order dengan tracking number {$trackingNumber} tidak ditemukan");
                    }

                    if ($order->is_packed) {
                        throw new \RuntimeException("Order dengan tracking number {$trackingNumber} sudah berstatus packed");
                    }

                    if ($order->items->isEmpty()) {
                        throw new \RuntimeException("Order dengan tracking number {$trackingNumber} tidak memiliki item");
                    }

                    if ($this->hasBelumBarcodeLog($order->id, true)) {
                        throw new \RuntimeException("Order dengan tracking number {$trackingNumber} sudah pernah disubmit melalui packing belum barcode");
                    }

                    $orders[] = $order;
                }

                foreach ($orders as $order) {
                    OrderLog::create([
                        'order_id' => $order->id,
                        'action' => 'scan_validasi_belum_barcode',
                        'performed_by' => $scannerName,
                        'notes' => 'Order berhasil dicatat melalui packing belum barcode dan siap dilanjutkan ke proses packing',
                    ]);
                }

                return collect($orders)
                    ->map(fn ($order) => $this->formatOrderPreview($order->fresh(['items'])))
                    ->values()
                    ->all();
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses scan produk belum barcode',
            ], 500);
        }

        return response()->json([
            'message' => 'Semua tracking number berhasil dicatat melalui packing belum barcode',
            'processed_count' => count($result),
            'orders' => $result,
        ]);
    }

    private function formatOrderPreview(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'total_qty' => (int) ($order->total_qty ?? 0),
            'status' => $order->status,
            'items' => $order->items
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'sku' => $item->sku,
                        'product_name' => $item->product_name,
                        'quantity' => (int) ($item->quantity ?? 0),
                        'image' => $this->normalizeImageUrl($item->image),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function findOrderByTracking(string $trackingNumber): ?Order
    {
        if ($trackingNumber === '') {
            return null;
        }

        $query = Order::with('items');

        $order = (clone $query)
            ->where('tracking_number', $trackingNumber)
            ->first();

        if ($order) {
            return $order;
        }

        return (clone $query)
            ->whereNotNull('tracking_number')
            ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
            ->first();
    }

    private function findOrderByTrackingForUpdate(string $trackingNumber): ?Order
    {
        if ($trackingNumber === '') {
            return null;
        }

        $query = Order::with('items')->lockForUpdate();

        $order = (clone $query)
            ->where('tracking_number', $trackingNumber)
            ->first();

        if ($order) {
            return $order;
        }

        return (clone $query)
            ->whereNotNull('tracking_number')
            ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
            ->first();
    }

    private function hasBelumBarcodeLog(int $orderId, bool $lock = false): bool
    {
        $query = OrderLog::query()
            ->where('order_id', $orderId)
            ->where('action', 'scan_validasi_belum_barcode');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    private function normalizeTrackingNumber($trackingNumber): string
    {
        return trim(urldecode((string) $trackingNumber));
    }

    private function normalizeImageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
