<?php

namespace App\Services;

use App\Models\GudangProdukActivityLog;
use App\Models\GudangProdukWorkspaceStockEntry;
use App\Models\Order;
use App\Models\Sku;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GudangProdukPackingStockService
{
    public function deductOrderStock(Order $order, string $notesPrefix, bool $allowPartial = true): array
    {
        $order->loadMissing('items');

        $requests = $order->items
            ->map(function ($item) {
                return [
                    'sku' => trim((string) ($item->sku ?? '')),
                    'qty' => (int) ($item->quantity ?? 0),
                ];
            })
            ->values()
            ->all();

        return $this->deductSkuQuantities($requests, $notesPrefix, $allowPartial);
    }

    public function deductSkuQuantities(array $requests, string $notesPrefix, bool $allowPartial = false): array
    {
        $normalizedRequests = collect($requests)
            ->map(function ($request) {
                return [
                    'sku' => trim((string) ($request['sku'] ?? '')),
                    'qty' => (int) ($request['qty'] ?? 0),
                ];
            })
            ->filter(fn ($request) => $request['sku'] !== '' && $request['qty'] > 0)
            ->values();

        if ($normalizedRequests->isEmpty()) {
            return [
                'summary' => $this->emptySummary(),
                'rows' => [],
            ];
        }

        $skuModels = $this->resolveSkuModels(
            $normalizedRequests->pluck('sku')->unique()->values()->all()
        );
        $aggregatedRequests = [];

        foreach ($normalizedRequests as $request) {
            $sku = $request['sku'];
            $skuModel = $skuModels[$sku] ?? null;
            $key = $skuModel
                ? 'sku:' . $skuModel->id
                : 'raw:' . ($this->normalizeSku($sku) ?: $sku);

            if (!isset($aggregatedRequests[$key])) {
                $aggregatedRequests[$key] = [
                    'sku' => $skuModel?->sku ?: $sku,
                    'sku_model' => $skuModel,
                    'qty' => 0,
                ];
            }

            $aggregatedRequests[$key]['qty'] += (int) $request['qty'];
        }

        $summary = $this->emptySummary();
        $rows = [];

        foreach ($aggregatedRequests as $request) {
            $requiredQty = (int) $request['qty'];
            $sku = trim((string) $request['sku']);
            $skuModel = $request['sku_model'];

            if (!$skuModel) {
                $summary['unmanaged_qty'] += $requiredQty;
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

            $stockEntries = GudangProdukWorkspaceStockEntry::query()
                ->where('sku_id', $skuModel->id)
                ->where('qty', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $availableQty = (int) $stockEntries->sum('qty');

            if (!$allowPartial && $availableQty > 0 && $availableQty < $requiredQty) {
                throw new \RuntimeException(
                    "Stok gudang produk untuk SKU {$skuModel->sku} tidak mencukupi. Stok tersedia: {$availableQty}, dibutuhkan: {$requiredQty}"
                );
            }

            $remainingToDeduct = min($requiredQty, $availableQty);
            $deductedQty = 0;

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

                $workspaceEntry->save();

                GudangProdukActivityLog::create([
                    'type' => 'packing_out',
                    'sku_id' => $skuModel->id,
                    'from_slot_id' => $slotId,
                    'to_slot_id' => null,
                    'qty' => $deductQty,
                    'notes' => "{$notesPrefix} - SKU: {$skuModel->sku}",
                    'created_by' => Auth::id(),
                ]);

                $deductedQty += $deductQty;
                $remainingToDeduct -= $deductQty;
            }

            $shortageQty = max($requiredQty - $deductedQty, 0);
            $summary['deducted_qty'] += $deductedQty;
            $summary['shortage_qty'] += $shortageQty;

            $status = 'deducted';
            $notes = 'Stok gudang produk berhasil dipotong';

            if ($deductedQty === 0) {
                $status = 'no_stock';
                $notes = 'Stok gudang produk tidak tersedia';
            } elseif ($shortageQty > 0) {
                $status = 'partial';
                $notes = "Stok gudang produk hanya terpotong {$deductedQty} dari {$requiredQty} pcs";
            }

            $rows[] = [
                'sku' => $skuModel->sku,
                'required_qty' => $requiredQty,
                'deducted_qty' => $deductedQty,
                'shortage_qty' => $shortageQty,
                'status' => $status,
                'notes' => $notes,
            ];
        }

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    public function emptySummary(): array
    {
        return [
            'deducted_qty' => 0,
            'shortage_qty' => 0,
            'unmanaged_qty' => 0,
        ];
    }

    public function mergeSummary(array $base, array $next): array
    {
        return [
            'deducted_qty' => (int) ($base['deducted_qty'] ?? 0) + (int) ($next['deducted_qty'] ?? 0),
            'shortage_qty' => (int) ($base['shortage_qty'] ?? 0) + (int) ($next['shortage_qty'] ?? 0),
            'unmanaged_qty' => (int) ($base['unmanaged_qty'] ?? 0) + (int) ($next['unmanaged_qty'] ?? 0),
        ];
    }

    public function buildSummaryText(array $summary): string
    {
        $parts = [];

        if ((int) ($summary['deducted_qty'] ?? 0) > 0) {
            $parts[] = 'Stok gudang terpotong ' . (int) $summary['deducted_qty'] . ' pcs';
        }

        if ((int) ($summary['shortage_qty'] ?? 0) > 0) {
            $parts[] = 'Stok gudang belum tersedia ' . (int) $summary['shortage_qty'] . ' pcs';
        }

        if ((int) ($summary['unmanaged_qty'] ?? 0) > 0) {
            $parts[] = 'SKU tanpa master stok ' . (int) $summary['unmanaged_qty'] . ' pcs';
        }

        return implode('. ', $parts);
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

    private function normalizeSku(?string $sku): string
    {
        return preg_replace('/\s+/', ' ', Str::upper(trim((string) $sku))) ?? '';
    }
}
