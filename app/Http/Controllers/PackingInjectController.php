<?php

namespace App\Http\Controllers;

use App\Models\GudangProdukActivityLog;
use App\Models\GudangProdukWorkspaceStockEntry;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackingInjectController extends Controller
{
    public function showByTracking($trackingNumber)
    {
        $order = $this->findOrderByTracking($this->normalizeTrackingNumber($trackingNumber));

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if ($order->is_packed) {
            return response()->json([
                'message' => 'Order ini sudah berstatus packed dan tidak bisa diinject ulang.',
            ], 409);
        }

        if ($order->items->isEmpty()) {
            return response()->json([
                'message' => 'Order ini tidak memiliki item untuk diinject.',
            ], 422);
        }

        if ($this->hasInjectLog($order->id)) {
            return response()->json([
                'message' => 'Order ini sudah pernah diproses melalui Inject Data.',
            ], 409);
        }

        return response()->json($this->formatOrderPreview($order));
    }

    public function submit(Request $request)
    {
        try {
            $request->validate([
                'tracking_numbers' => 'required|array|min:1|max:1000',
                'tracking_numbers.*' => 'required|string|min:1|max:255',
            ], [
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
            $result = DB::transaction(function () use ($trackingNumbers) {
                $orders = [];

                foreach ($trackingNumbers as $trackingNumber) {
                    $order = $this->findOrderByTrackingForUpdate($trackingNumber);

                    if (!$order) {
                        throw new \RuntimeException("Order dengan tracking number {$trackingNumber} tidak ditemukan");
                    }

                    if ($order->is_packed) {
                        throw new \RuntimeException("Order dengan tracking number {$trackingNumber} sudah berstatus packed");
                    }

                    if ($order->items->isEmpty()) {
                        throw new \RuntimeException("Order dengan tracking number {$trackingNumber} tidak memiliki item");
                    }

                    if ($this->hasInjectLog($order->id, true)) {
                        throw new \RuntimeException("Order dengan tracking number {$trackingNumber} sudah pernah diproses melalui Inject Data");
                    }

                    $orders[] = $order;
                }

                $processedOrders = [];
                $totalDeductedQty = 0;
                $totalShortageQty = 0;
                $totalUnmanagedQty = 0;

                foreach ($orders as $order) {
                    $deduction = $this->deductOrderStock($order);
                    $summary = $deduction['summary'];
                    $totalDeductedQty += $summary['deducted_qty'];
                    $totalShortageQty += $summary['shortage_qty'];
                    $totalUnmanagedQty += $summary['unmanaged_qty'];

                    $order->update(['is_packed' => 1]);

                    OrderLog::create([
                        'order_id' => $order->id,
                        'action' => 'scan_validasi_inject',
                        'performed_by' => Auth::user()->name ?? 'System',
                        'notes' => $this->buildInjectNotes($summary),
                    ]);

                    $processedOrders[] = [
                        'order' => $this->formatOrderPreview($order->fresh(['items'])),
                        'deduction' => $deduction,
                    ];
                }

                return [
                    'orders' => $processedOrders,
                    'summary' => [
                        'deducted_qty' => $totalDeductedQty,
                        'shortage_qty' => $totalShortageQty,
                        'unmanaged_qty' => $totalUnmanagedQty,
                    ],
                ];
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses Inject Data',
            ], 500);
        }

        return response()->json([
            'message' => 'Semua tracking number berhasil diinject dan order ditandai packed',
            'processed_count' => count($result['orders']),
            'summary' => $result['summary'],
            'orders' => $result['orders'],
        ]);
    }

    private function deductOrderStock(Order $order): array
    {
        $order->loadMissing('items');

        $skuList = $order->items
            ->pluck('sku')
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $skuModels = $this->resolveSkuModels($skuList);
        $rows = [];
        $deductedQty = 0;
        $shortageQty = 0;
        $unmanagedQty = 0;

        foreach ($order->items as $item) {
            $sku = trim((string) $item->sku);
            $requiredQty = (int) ($item->quantity ?? 0);
            $skuModel = $skuModels[$sku] ?? null;

            if (!$skuModel) {
                $unmanagedQty += $requiredQty;
                $rows[] = [
                    'sku' => $sku,
                    'required_qty' => $requiredQty,
                    'deducted_qty' => 0,
                    'shortage_qty' => 0,
                    'status' => 'unmanaged',
                    'notes' => 'SKU belum terdaftar di master stok gudang produk',
                ];
                continue;
            }

            $remainingQty = $requiredQty;
            $itemDeductedQty = 0;

            $stockEntries = GudangProdukWorkspaceStockEntry::query()
                ->where('sku_id', $skuModel->id)
                ->where('qty', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($stockEntries as $workspaceEntry) {
                if ($remainingQty <= 0) {
                    break;
                }

                $availableQty = (int) $workspaceEntry->qty;
                $deductQty = min($availableQty, $remainingQty);

                if ($deductQty <= 0) {
                    continue;
                }

                $slotId = $workspaceEntry->slot_id;
                $workspaceEntry->qty -= $deductQty;

                if ($workspaceEntry->qty <= 0) {
                    $workspaceEntry->delete();
                } else {
                    $workspaceEntry->save();
                }

                GudangProdukActivityLog::create([
                    'type' => 'packing_out',
                    'sku_id' => $skuModel->id,
                    'from_slot_id' => $slotId,
                    'to_slot_id' => null,
                    'qty' => $deductQty,
                    'notes' => "Inject data order #{$order->order_number} - SKU: {$skuModel->sku}",
                    'created_by' => Auth::id(),
                ]);

                $itemDeductedQty += $deductQty;
                $remainingQty -= $deductQty;
            }

            $itemShortageQty = max($remainingQty, 0);
            $deductedQty += $itemDeductedQty;
            $shortageQty += $itemShortageQty;

            $status = 'deducted';
            $notes = 'Stok gudang produk berhasil dipotong';

            if ($itemDeductedQty === 0) {
                $status = 'no_stock';
                $notes = 'Stok gudang produk tidak tersedia, order tetap diinject';
            } elseif ($itemShortageQty > 0) {
                $status = 'partial';
                $notes = "Stok gudang produk hanya terpotong {$itemDeductedQty} dari {$requiredQty} pcs";
            }

            $rows[] = [
                'sku' => $skuModel->sku,
                'required_qty' => $requiredQty,
                'deducted_qty' => $itemDeductedQty,
                'shortage_qty' => $itemShortageQty,
                'status' => $status,
                'notes' => $notes,
            ];
        }

        return [
            'summary' => [
                'deducted_qty' => $deductedQty,
                'shortage_qty' => $shortageQty,
                'unmanaged_qty' => $unmanagedQty,
            ],
            'rows' => $rows,
        ];
    }

    private function formatOrderPreview(Order $order): array
    {
        $order->loadMissing('items');

        $skuList = $order->items
            ->pluck('sku')
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $skuModels = $this->resolveSkuModels($skuList);
        $stockAvailableBySkuId = $this->getStockAvailableBySkuId($skuModels);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'total_qty' => (int) ($order->total_qty ?? 0),
            'status' => $order->status,
            'items' => $order->items
                ->map(function ($item) use ($skuModels, $stockAvailableBySkuId) {
                    $quantity = (int) ($item->quantity ?? 0);
                    $sku = trim((string) $item->sku);
                    $skuModel = $skuModels[$sku] ?? null;
                    $stockAvailableQty = $skuModel ? (int) ($stockAvailableBySkuId[$skuModel->id] ?? 0) : null;
                    $estimatedDeductQty = $stockAvailableQty === null ? 0 : min($quantity, $stockAvailableQty);
                    $stockNote = 'Stok tersedia';

                    if (!$skuModel) {
                        $stockNote = 'SKU belum terdaftar di master stok';
                    } elseif ($stockAvailableQty <= 0) {
                        $stockNote = 'Stok gudang tidak ada, order tetap bisa diinject';
                    } elseif ($stockAvailableQty < $quantity) {
                        $stockNote = "Stok kurang, estimasi potong {$stockAvailableQty} dari {$quantity} pcs";
                    }

                    return [
                        'id' => $item->id,
                        'sku' => $item->sku,
                        'product_name' => $item->product_name,
                        'quantity' => $quantity,
                        'image' => $this->normalizeImageUrl($item->image),
                        'stock_available_qty' => $stockAvailableQty,
                        'estimated_deduct_qty' => $estimatedDeductQty,
                        'stock_note' => $stockNote,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function resolveSkuModels(array $skuList): array
    {
        $skuList = collect($skuList)
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();

        $exactModels = Sku::whereIn('sku', $skuList)->get()->keyBy('sku');
        $resolvedModels = [];

        foreach ($skuList as $sku) {
            if ($exactModels->has($sku)) {
                $resolvedModels[$sku] = $exactModels[$sku];
            }
        }

        $remaining = $skuList->filter(fn ($sku) => !isset($resolvedModels[$sku]))->values();

        if ($remaining->isEmpty()) {
            return $resolvedModels;
        }

        $normalizedIndex = [];
        foreach (Sku::query()->get(['id', 'sku']) as $skuModel) {
            $normalizedSku = $this->normalizeSku($skuModel->sku);

            if ($normalizedSku !== '' && !isset($normalizedIndex[$normalizedSku])) {
                $normalizedIndex[$normalizedSku] = $skuModel;
            }
        }

        foreach ($remaining as $sku) {
            $resolvedModels[$sku] = $normalizedIndex[$this->normalizeSku($sku)] ?? null;
        }

        return $resolvedModels;
    }

    private function getStockAvailableBySkuId(array $skuModels): array
    {
        $skuIds = collect($skuModels)
            ->filter(fn ($skuModel) => !empty($skuModel?->id))
            ->pluck('id')
            ->unique()
            ->values();

        if ($skuIds->isEmpty()) {
            return [];
        }

        return GudangProdukWorkspaceStockEntry::query()
            ->whereIn('sku_id', $skuIds)
            ->selectRaw('sku_id, COALESCE(SUM(qty), 0) as total_qty')
            ->groupBy('sku_id')
            ->pluck('total_qty', 'sku_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
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

    private function hasInjectLog(int $orderId, bool $lock = false): bool
    {
        $query = OrderLog::query()
            ->where('order_id', $orderId)
            ->where('action', 'scan_validasi_inject');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    private function buildInjectNotes(array $summary): string
    {
        $notes = ['Order berhasil diinject dari data ekspedisi'];

        if (($summary['deducted_qty'] ?? 0) > 0) {
            $notes[] = 'Stok gudang terpotong ' . $summary['deducted_qty'] . ' pcs';
        }

        if (($summary['shortage_qty'] ?? 0) > 0) {
            $notes[] = 'Stok gudang tidak tersedia ' . $summary['shortage_qty'] . ' pcs';
        }

        if (($summary['unmanaged_qty'] ?? 0) > 0) {
            $notes[] = 'SKU tanpa master stok ' . $summary['unmanaged_qty'] . ' pcs';
        }

        return implode('. ', $notes);
    }

    private function normalizeTrackingNumber($trackingNumber): string
    {
        return trim(urldecode((string) $trackingNumber));
    }

    private function normalizeSku(?string $sku): string
    {
        return preg_replace('/\s+/', ' ', Str::upper(trim((string) $sku))) ?? '';
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
