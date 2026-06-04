<?php

namespace App\Http\Controllers;

use App\Models\GudangProdukActivityLog;
use App\Models\GudangProdukDetail;
use App\Models\GudangProdukWorkspaceStockEntry;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderPackingResult;
use App\Models\ProdukSku;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackingRandomController extends Controller
{
    protected function getMismatchStatus(): string
    {
        return 'random';
    }

    protected function getStatusValidationRule(): string
    {
        return 'required|string|in:sesuai,' . $this->getMismatchStatus() . ',bonus';
    }

    protected function getSuccessMessage(): string
    {
        return 'Order berhasil divalidasi melalui packing random';
    }

    protected function getLogAction(): string
    {
        return 'scan_validasi_random';
    }

    public function showByTracking($trackingNumber)
    {
        $order = Order::with('items')
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if ($order->is_packed) {
            return response()->json(['message' => 'Order ini sudah berstatus packed dan tidak bisa discan ulang.'], 409);
        }

        return response()->json($this->formatOrder($order));
    }

    public function resolveSku($sku)
    {
        return response()->json($this->resolveSkuMetadata(trim((string) $sku)));
    }

    public function validateScan(Request $request, $trackingNumber)
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1|max:1000',
                'items.*.order_item_id' => 'nullable|integer',
                'items.*.actual_sku' => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1|max:10000',
                'items.*.status' => $this->getStatusValidationRule(),
                'items.*.serials' => 'required|array|min:1',
                'items.*.serials.*' => 'required|string|min:1|max:255',
            ], [
                'items.required' => 'Data items tidak boleh kosong',
                'items.array' => 'Data items harus berupa array',
                'items.min' => 'Minimal harus ada 1 item',
                'items.max' => 'Maksimal 1000 item per request',
                'items.*.actual_sku.required' => 'SKU aktual tidak boleh kosong',
                'items.*.quantity.required' => 'Quantity tidak boleh kosong',
                'items.*.quantity.integer' => 'Quantity harus berupa angka',
                'items.*.quantity.min' => 'Quantity minimal 1',
                'items.*.status.in' => 'Status item tidak valid',
                'items.*.serials.required' => 'Nomor seri tidak boleh kosong',
                'items.*.serials.array' => 'Nomor seri harus berupa array',
                'items.*.serials.min' => 'Minimal harus ada 1 nomor seri',
                'items.*.serials.*.required' => 'Nomor seri tidak boleh kosong',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors(),
            ], 422);
        }

        $order = Order::with('items')
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if ($order->is_packed) {
            return response()->json([
                'message' => 'Order ini sudah berstatus packed dan tidak bisa divalidasi ulang.',
            ], 422);
        }

        $orderItems = $order->items->keyBy('id');
        if ($orderItems->isEmpty()) {
            return response()->json(['message' => 'Order tidak memiliki item untuk dipacking'], 422);
        }

        $skuList = collect($request->items)
            ->pluck('actual_sku')
            ->map(fn($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $resolvedSkuModels = $this->resolveSkuModels($skuList);
        $skuMetadataMap = $this->resolveSkuMetadataMap($skuList, $resolvedSkuModels);
        $stockRequestBySkuId = [];
        $serialRegistry = [];
        $groupedOrderQty = [];
        $normalizedItems = [];
        $unmanagedSkus = [];

        $mismatchStatus = $this->getMismatchStatus();

        foreach ($request->items as $index => $item) {
            $status = strtolower(trim((string) $item['status']));
            $actualSku = trim((string) $item['actual_sku']);
            $quantity = (int) $item['quantity'];
            $orderItemId = $item['order_item_id'] ?? null;
            $serials = collect($item['serials'] ?? [])
                ->map(fn($serial) => trim((string) $serial))
                ->values()
                ->all();

            if (count($serials) !== $quantity) {
                return response()->json([
                    'message' => "Jumlah nomor seri untuk baris ke-" . ($index + 1) . " harus sama dengan quantity",
                ], 422);
            }

            foreach ($serials as $serial) {
                if ($serial === '') {
                    return response()->json([
                        'message' => "Nomor seri pada baris ke-" . ($index + 1) . " tidak boleh kosong",
                    ], 422);
                }

                if (isset($serialRegistry[$serial])) {
                    return response()->json([
                        'message' => "Nomor seri {$serial} terdeteksi duplikat dalam transaksi ini",
                    ], 422);
                }

                $serialRegistry[$serial] = true;
            }

            $metadata = $skuMetadataMap[$actualSku] ?? $this->buildFallbackSkuMetadata($actualSku);

            if (!empty($metadata['sku_id'])) {
                $stockRequestBySkuId[$metadata['sku_id']] = [
                    'sku' => $metadata['resolved_sku'] ?? $actualSku,
                    'qty' => ($stockRequestBySkuId[$metadata['sku_id']]['qty'] ?? 0) + $quantity,
                ];
            } else {
                $unmanagedSkus[$actualSku] = true;
            }

            if ($status === 'bonus') {
                if (!empty($orderItemId)) {
                    return response()->json([
                        'message' => 'Baris bonus tidak boleh terhubung ke item order asli',
                    ], 422);
                }

                $normalizedItems[] = [
                    'order_item_id' => null,
                    'line_type' => 'bonus',
                    'status' => 'bonus',
                    'original_sku' => null,
                    'original_product_name' => null,
                    'original_image' => null,
                    'actual_sku' => $actualSku,
                    'actual_sku_id' => $metadata['sku_id'],
                    'actual_product_name' => $metadata['product_name'] ?: $actualSku,
                    'actual_image' => $metadata['image'],
                    'ordered_qty' => 0,
                    'scanned_qty' => $quantity,
                    'serials' => $serials,
                ];

                continue;
            }

            if (!$orderItemId || !$orderItems->has($orderItemId)) {
                return response()->json([
                    'message' => "Item order pada baris ke-" . ($index + 1) . " tidak valid",
                ], 422);
            }

            $orderItem = $orderItems[$orderItemId];
            $groupedOrderQty[$orderItemId] = ($groupedOrderQty[$orderItemId] ?? 0) + $quantity;

            if ($status === 'sesuai' && !$this->isSameSku($actualSku, $orderItem->sku)) {
                return response()->json([
                    'message' => "Baris ke-" . ($index + 1) . " berstatus sesuai tetapi SKU barcode tidak cocok dengan SKU order",
                ], 422);
            }

            if ($status === $mismatchStatus && $this->isSameSku($actualSku, $orderItem->sku)) {
                return response()->json([
                    'message' => "Baris ke-" . ($index + 1) . " berstatus {$mismatchStatus} tetapi SKU barcode sebenarnya sama dengan SKU order",
                ], 422);
            }

            $actualProductName = $metadata['product_name'] ?: $actualSku;
            $actualImage = $metadata['image'];

            if ($status === 'sesuai') {
                $actualProductName = $orderItem->product_name;
                $actualImage = $this->normalizeImageUrl($orderItem->image);
            }

            $normalizedItems[] = [
                'order_item_id' => $orderItem->id,
                'line_type' => 'order_item',
                'status' => $status,
                'original_sku' => $orderItem->sku,
                'original_product_name' => $orderItem->product_name,
                'original_image' => $this->normalizeImageUrl($orderItem->image),
                'actual_sku' => $actualSku,
                'actual_sku_id' => $metadata['sku_id'],
                'actual_product_name' => $actualProductName,
                'actual_image' => $actualImage,
                'ordered_qty' => $quantity,
                'scanned_qty' => $quantity,
                'serials' => $serials,
            ];
        }

        foreach ($orderItems as $orderItem) {
            $capturedQty = (int) ($groupedOrderQty[$orderItem->id] ?? 0);
            if ($capturedQty !== (int) $orderItem->quantity) {
                return response()->json([
                    'message' => "Qty fulfillment untuk SKU {$orderItem->sku} harus tepat {$orderItem->quantity}, saat ini {$capturedQty}",
                ], 422);
            }
        }

        $usedSerialMessage = $this->getUsedSerialMessage($normalizedItems);
        if ($usedSerialMessage) {
            return response()->json([
                'message' => $usedSerialMessage,
            ], 422);
        }

        $successMessage = $this->getSuccessMessage();
        $logAction = $this->getLogAction();

        try {
            DB::transaction(function () use ($order, $stockRequestBySkuId, $normalizedItems, $unmanagedSkus, $successMessage, $logAction) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

                if (!$lockedOrder || $lockedOrder->is_packed) {
                    throw new \Exception('Order ini sudah berstatus packed dan tidak bisa divalidasi ulang.');
                }

                $usedSerialMessage = $this->getUsedSerialMessage($normalizedItems);
                if ($usedSerialMessage) {
                    throw new \Exception($usedSerialMessage);
                }

                foreach ($stockRequestBySkuId as $skuId => $stockRequest) {
                    $stockEntries = GudangProdukWorkspaceStockEntry::where('sku_id', $skuId)
                        ->where('qty', '>', 0)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    // Jika tidak ada stok di workspace, lanjutkan saja
                    // (barang belum di-input ke sistem gudang, bukan error)
                    if ($stockEntries->isEmpty()) {
                        continue;
                    }

                    $availableQty = (int) $stockEntries->sum('qty');
                    $requiredQty = (int) $stockRequest['qty'];

                    // Stok ada tapi kurang — ini baru jadi error
                    if ($availableQty < $requiredQty) {
                        throw new \Exception("Stok gudang produk untuk SKU {$stockRequest['sku']} tidak mencukupi. Stok tersedia: {$availableQty}, dibutuhkan: {$requiredQty}");
                    }

                    $remainingToDeduct = $requiredQty;

                    foreach ($stockEntries as $workspaceEntry) {
                        if ($remainingToDeduct <= 0) {
                            break;
                        }

                        $entryQty = (int) $workspaceEntry->qty;
                        $deductQty = min($entryQty, $remainingToDeduct);

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
                            'sku_id' => $skuId,
                            'from_slot_id' => $slotId,
                            'to_slot_id' => null,
                            'qty' => $deductQty,
                            'notes' => "Packing order #{$order->order_number} - SKU: {$stockRequest['sku']}",
                            'created_by' => Auth::id(),
                        ]);

                        $remainingToDeduct -= $deductQty;
                    }
                }

                OrderPackingResult::where('order_id', $order->id)->delete();

                foreach ($normalizedItems as $item) {
                    $serials = $item['serials'];
                    unset($item['serials']);

                    $packingResult = OrderPackingResult::create(array_merge($item, [
                        'order_id' => $order->id,
                    ]));

                    $packingResult->serials()->createMany(
                        collect($serials)->map(fn($serial) => ['serial_number' => $serial])->all()
                    );
                }

                $lockedOrder->update(['is_packed' => 1]);

                $notes = $successMessage;
                if (!empty($unmanagedSkus)) {
                    $notes .= '. SKU tanpa master stok: ' . implode(', ', array_keys($unmanagedSkus));
                }

                OrderLog::create([
                    'order_id' => $order->id,
                    'action' => $logAction,
                    'performed_by' => Auth::user()->name ?? 'System',
                    'notes' => $notes,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => $this->getSuccessMessage(),
            'order' => $order->fresh([
                'items',
                'packingResults.serials',
            ]),
        ]);
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'platform' => $order->platform,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'total_amount' => $order->total_amount,
            'status' => $order->status,
            'order_date' => $order->order_date,
            'total_qty' => $order->total_qty,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'image' => $this->normalizeImageUrl($item->image),
                ];
            })->values()->all(),
        ];
    }

    private function resolveSkuModels(array $skuList): array
    {
        $skuList = collect($skuList)
            ->map(fn($sku) => trim((string) $sku))
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

        $remaining = $skuList->filter(fn($sku) => !isset($resolvedModels[$sku]))->values();

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

    private function resolveSkuMetadataMap(array $skuList, array $skuModels = []): array
    {
        $skuList = collect($skuList)
            ->map(fn($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();

        $skuModels = !empty($skuModels) ? $skuModels : $this->resolveSkuModels($skuList->all());

        $resolvedModels = collect($skuModels)
            ->filter(fn($skuModel) => !empty($skuModel?->id))
            ->unique('id')
            ->values();

        $resolvedSkuStrings = $resolvedModels->pluck('sku');
        $resolvedSkuIds = $resolvedModels->pluck('id');

        $produkSkus = ProdukSku::whereIn('sku', $resolvedSkuStrings)
            ->with('produk')
            ->get()
            ->keyBy('sku');

        $gudangPhotos = GudangProdukDetail::whereIn('sku_id', $resolvedSkuIds)
            ->whereNotNull('foto')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('sku_id');

        $result = [];
        foreach ($skuList as $sku) {
            $skuModel = $skuModels[$sku] ?? null;
            $metadata = $this->buildFallbackSkuMetadata($sku);

            if ($skuModel) {
                $produkSku = $produkSkus[$skuModel->sku] ?? null;
                $photoPath = $gudangPhotos->get($skuModel->id)?->first()?->foto;

                $metadata['sku_id'] = $skuModel->id;
                $metadata['resolved_sku'] = $skuModel->sku;
                $metadata['stock_managed'] = true;

                if ($produkSku && $produkSku->produk) {
                    $warna = strtoupper(trim((string) ($produkSku->warna ?? '')));
                    $ukuran = strtoupper(trim((string) ($produkSku->ukuran ?? '')));
                    $suffix = trim(collect([$warna, $ukuran])->filter()->implode(' '));
                    $metadata['product_name'] = trim($produkSku->produk->nama_produk . ($suffix ? " - {$suffix}" : ''));
                    $metadata['image'] = $this->normalizeImageUrl($produkSku->produk->gambar_produk);
                }

                if ($photoPath) {
                    $metadata['image'] = $this->normalizeImageUrl($photoPath);
                }
            }

            $result[$sku] = $metadata;
        }

        return $result;
    }

    private function resolveSkuMetadata(string $sku): array
    {
        if ($sku === '') {
            return $this->buildFallbackSkuMetadata($sku);
        }

        return $this->resolveSkuMetadataMap([$sku])[$sku] ?? $this->buildFallbackSkuMetadata($sku);
    }

    private function buildFallbackSkuMetadata(?string $sku): array
    {
        $sku = trim((string) $sku);

        return [
            'sku' => $sku,
            'sku_id' => null,
            'resolved_sku' => null,
            'product_name' => $sku,
            'image' => null,
            'stock_managed' => false,
        ];
    }

    private function isSameSku(?string $left, ?string $right): bool
    {
        $left = $this->normalizeSku($left);
        $right = $this->normalizeSku($right);

        return $left !== '' && $left === $right;
    }

    private function normalizeSku(?string $sku): string
    {
        return preg_replace('/\s+/', ' ', Str::upper(trim((string) $sku))) ?? '';
    }

    private function normalizeSerialNumber($serialNumber): string
    {
        return Str::upper(trim((string) $serialNumber));
    }

    private function getUsedSerialMessage(array $items): ?string
    {
        return null;
    }

    private function collectSerialLookup(array $items): array
    {
        $serials = [];

        foreach ($items as $item) {
            foreach (($item['serials'] ?? []) as $serial) {
                $normalizedSerial = $this->normalizeSerialNumber($serial);

                if ($normalizedSerial !== '') {
                    $serials[$normalizedSerial] = $serial;
                }
            }
        }

        return $serials;
    }

    private function formatUsedSerialMessage($serial, $sku = null, $trackingNumber = null, $orderNumber = null): string
    {
        $context = [];

        if ($sku) {
            $context[] = "SKU {$sku}";
        }

        if ($trackingNumber) {
            $context[] = "tracking {$trackingNumber}";
        } elseif ($orderNumber) {
            $context[] = "order {$orderNumber}";
        }

        $suffix = empty($context) ? '' : ' di ' . implode(', ', $context);

        return "Nomor seri {$serial} sudah pernah digunakan{$suffix} dan tidak bisa digunakan lagi.";
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
