<?php

namespace App\Http\Controllers;

use App\Models\GudangProdukActivityLog;
use App\Models\GudangProdukLayout;
use App\Models\GudangProdukLayoutBlock;
use App\Models\GudangProdukLayoutFloor;
use App\Models\GudangProdukLayoutRack;
use App\Models\GudangProdukSlotAlias;
use App\Models\GudangProdukWorkspaceStockEntry;
use App\Models\Produk;
use App\Models\ProdukSku;
use App\Models\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GudangProdukWorkspaceController extends Controller
{
    private const DEFAULT_CANVAS_COLUMNS = 12;
    private const DEFAULT_CANVAS_ROWS = 10;

    public function index()
    {
        return response()->json([
            'data' => $this->buildWorkspaceSnapshot(),
        ]);
    }

    public function storeLayout(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $payload = $this->validateLayoutPayload($request, true);
        $this->ensureUniqueLayoutStructure($payload);

        $layoutUid = $payload['id'] ?? ('layout_' . Str::lower(Str::random(12)));
        if (GudangProdukLayout::where('uid', $layoutUid)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['ID layout sudah dipakai.'],
            ]);
        }

        $payload['id'] = $layoutUid;
        $layout = $this->saveLayoutPayload($payload, null);

        return response()->json([
            'message' => 'Master gudang berhasil dibuat.',
            'data' => $this->buildWorkspaceSnapshot(),
            'layout' => $this->transformLayout($layout->fresh([
                'floors.blocks.racks',
                'slotAliases',
            ])),
        ], 201);
    }

    public function updateLayout(Request $request, string $layoutUid)
    {
        $this->ensureWorkspaceTablesReady();

        $payload = $this->validateLayoutPayload($request, true);
        $this->ensureUniqueLayoutStructure($payload);

        $layout = GudangProdukLayout::with(['floors.blocks.racks', 'slotAliases'])
            ->where('uid', $layoutUid)
            ->firstOrFail();

        $payload['id'] = $layoutUid;
        $updatedLayout = $this->saveLayoutPayload($payload, $layout);

        return response()->json([
            'message' => 'Layout gudang berhasil diperbarui.',
            'data' => $this->buildWorkspaceSnapshot(),
            'layout' => $this->transformLayout($updatedLayout->fresh([
                'floors.blocks.racks',
                'slotAliases',
            ])),
        ]);
    }

    public function placeStock(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'layoutId' => 'required|string|max:255',
            'slotId' => 'required|string|max:255',
            'skuId' => 'required|integer|exists:skus,id',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $layout = GudangProdukLayout::with(['floors.blocks.racks'])
            ->where('uid', $validated['layoutId'])
            ->firstOrFail();

        $validSlotIds = $this->buildSlotIdsFromLayoutModel($layout);
        if (!in_array($validated['slotId'], $validSlotIds, true)) {
            throw ValidationException::withMessages([
                'slotId' => ['Slot tujuan tidak ditemukan pada layout yang dipilih.'],
            ]);
        }

        DB::transaction(function () use ($layout, $validated) {
            $entry = GudangProdukWorkspaceStockEntry::firstOrNew([
                'layout_id' => $layout->id,
                'slot_id' => $validated['slotId'],
                'sku_id' => $validated['skuId'],
            ]);

            $entry->qty = (int) ($entry->qty ?? 0) + (int) $validated['qty'];
            $entry->updated_by = auth()->id();
            $entry->save();

            GudangProdukActivityLog::create([
                'type' => 'placement',
                'sku_id' => $validated['skuId'],
                'from_slot_id' => null,
                'to_slot_id' => $validated['slotId'],
                'qty' => $validated['qty'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => 'Placement gudang berhasil disimpan.',
            'data' => $this->buildWorkspaceSnapshot(),
        ]);
    }

    public function mutateStock(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'skuId' => 'required|integer|exists:skus,id',
            'fromSlotId' => 'required|string|max:255',
            'toSlotId' => 'required|string|max:255|different:fromSlotId',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $sourceLayout = $this->findLayoutBySlotId($validated['fromSlotId']);
        $targetLayout = $this->findLayoutBySlotId($validated['toSlotId']);

        if (!$sourceLayout || !$targetLayout) {
            throw ValidationException::withMessages([
                'slot' => ['Slot asal atau tujuan tidak valid.'],
            ]);
        }

        if (!in_array($validated['fromSlotId'], $this->buildSlotIdsFromLayoutModel($sourceLayout), true)) {
            throw ValidationException::withMessages([
                'fromSlotId' => ['Slot asal tidak ditemukan pada layout saat ini.'],
            ]);
        }

        if (!in_array($validated['toSlotId'], $this->buildSlotIdsFromLayoutModel($targetLayout), true)) {
            throw ValidationException::withMessages([
                'toSlotId' => ['Slot tujuan tidak ditemukan pada layout saat ini.'],
            ]);
        }

        DB::transaction(function () use ($validated, $sourceLayout, $targetLayout) {
            $sourceEntry = GudangProdukWorkspaceStockEntry::where('layout_id', $sourceLayout->id)
                ->where('slot_id', $validated['fromSlotId'])
                ->where('sku_id', $validated['skuId'])
                ->lockForUpdate()
                ->first();

            if (!$sourceEntry || $sourceEntry->qty < (int) $validated['qty']) {
                throw ValidationException::withMessages([
                    'qty' => ['Stok di lokasi asal tidak mencukupi untuk mutasi.'],
                ]);
            }

            $sourceEntry->qty -= (int) $validated['qty'];
            $sourceEntry->updated_by = auth()->id();
            if ($sourceEntry->qty <= 0) {
                $sourceEntry->delete();
            } else {
                $sourceEntry->save();
            }

            $targetEntry = GudangProdukWorkspaceStockEntry::firstOrNew([
                'layout_id' => $targetLayout->id,
                'slot_id' => $validated['toSlotId'],
                'sku_id' => $validated['skuId'],
            ]);

            $targetEntry->qty = (int) ($targetEntry->qty ?? 0) + (int) $validated['qty'];
            $targetEntry->updated_by = auth()->id();
            $targetEntry->save();

            GudangProdukActivityLog::create([
                'type' => 'mutation',
                'sku_id' => $validated['skuId'],
                'from_slot_id' => $validated['fromSlotId'],
                'to_slot_id' => $validated['toSlotId'],
                'qty' => $validated['qty'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => 'Mutasi gudang berhasil disimpan.',
            'data' => $this->buildWorkspaceSnapshot(),
        ]);
    }

    private function validateLayoutPayload(Request $request, bool $requireName = false): array
    {
        return $request->validate([
            'id' => 'nullable|string|max:255',
            'name' => ($requireName ? 'required' : 'nullable') . '|string|max:255',
            'address' => 'nullable|string|max:255',
            'pic' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'slotAliases' => 'nullable|array',
            'floors' => 'nullable|array',
            'floors.*.id' => 'required|string|max:255',
            'floors.*.number' => 'required|integer|min:1',
            'floors.*.label' => 'nullable|string|max:255',
            'floors.*.blocks' => 'nullable|array',
            'floors.*.blocks.*.id' => 'required|string|max:255',
            'floors.*.blocks.*.code' => 'required|string|max:20',
            'floors.*.blocks.*.label' => 'nullable|string|max:255',
            'floors.*.blocks.*.layoutColumns' => 'nullable|integer|min:1|max:4',
            'floors.*.blocks.*.layoutCanvas' => 'nullable|array',
            'floors.*.blocks.*.layoutCanvas.columns' => 'nullable|integer|min:6|max:24',
            'floors.*.blocks.*.layoutCanvas.rows' => 'nullable|integer|min:4|max:18',
            'floors.*.blocks.*.racks' => 'nullable|array',
            'floors.*.blocks.*.racks.*.id' => 'required|string|max:255',
            'floors.*.blocks.*.racks.*.number' => 'required|integer|min:1',
            'floors.*.blocks.*.racks.*.rows' => 'required|integer|min:1',
            'floors.*.blocks.*.racks.*.label' => 'nullable|string|max:255',
            'floors.*.blocks.*.racks.*.layoutPosition' => 'nullable|array',
            'floors.*.blocks.*.racks.*.layoutPosition.x' => 'nullable|integer|min:1',
            'floors.*.blocks.*.racks.*.layoutPosition.y' => 'nullable|integer|min:1',
            'floors.*.blocks.*.racks.*.layoutPosition.w' => 'nullable|integer|min:2',
            'floors.*.blocks.*.racks.*.layoutPosition.h' => 'nullable|integer|min:2',
        ]);
    }

    private function ensureUniqueLayoutStructure(array $payload): void
    {
        $floorNumbers = [];
        foreach ($payload['floors'] ?? [] as $floor) {
            if (in_array($floor['number'], $floorNumbers, true)) {
                throw ValidationException::withMessages([
                    'floors' => ['Nomor lantai tidak boleh duplikat dalam satu layout.'],
                ]);
            }
            $floorNumbers[] = $floor['number'];

            $blockCodes = [];
            foreach ($floor['blocks'] ?? [] as $block) {
                $normalizedCode = strtoupper((string) $block['code']);
                if (in_array($normalizedCode, $blockCodes, true)) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Kode blok tidak boleh duplikat pada lantai yang sama.'],
                    ]);
                }
                $blockCodes[] = $normalizedCode;

                $rackNumbers = [];
                foreach ($block['racks'] ?? [] as $rack) {
                    if (in_array($rack['number'], $rackNumbers, true)) {
                        throw ValidationException::withMessages([
                            'racks' => ['Nomor rak tidak boleh duplikat pada blok yang sama.'],
                        ]);
                    }
                    $rackNumbers[] = $rack['number'];
                }
            }
        }
    }

    private function saveLayoutPayload(array $payload, ?GudangProdukLayout $layout): GudangProdukLayout
    {
        return DB::transaction(function () use ($payload, $layout) {
            if (!$layout) {
                $layout = GudangProdukLayout::create([
                    'uid' => $payload['id'],
                    'name' => $payload['name'],
                    'address' => $payload['address'] ?? null,
                    'pic' => $payload['pic'] ?? null,
                    'description' => $payload['description'] ?? null,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            } else {
                $layout->update([
                    'name' => $payload['name'],
                    'address' => $payload['address'] ?? null,
                    'pic' => $payload['pic'] ?? null,
                    'description' => $payload['description'] ?? null,
                    'updated_by' => auth()->id(),
                ]);
            }

            $layout->load(['floors.blocks.racks']);

            $incomingFloors = collect($payload['floors'] ?? []);
            $incomingFloorUids = $incomingFloors->pluck('id')->filter()->values()->all();
            if (count($incomingFloorUids) > 0) {
                $layout->floors()->whereNotIn('uid', $incomingFloorUids)->delete();
            } else {
                $layout->floors()->delete();
            }

            foreach ($incomingFloors as $floorIndex => $floorData) {
                $existingFloor = $layout->floors->firstWhere('uid', $floorData['id']);
                $floor = $existingFloor ?: new GudangProdukLayoutFloor([
                    'uid' => $floorData['id'],
                    'layout_id' => $layout->id,
                ]);

                $floor->fill([
                    'layout_id' => $layout->id,
                    'number' => (int) $floorData['number'],
                    'label' => $floorData['label'] ?? ('Lantai ' . $floorData['number']),
                    'sort_order' => $floorIndex,
                ]);
                $floor->save();

                $floor->load('blocks.racks');

                $incomingBlocks = collect($floorData['blocks'] ?? []);
                $incomingBlockUids = $incomingBlocks->pluck('id')->filter()->values()->all();
                if (count($incomingBlockUids) > 0) {
                    $floor->blocks()->whereNotIn('uid', $incomingBlockUids)->delete();
                } else {
                    $floor->blocks()->delete();
                }

                foreach ($incomingBlocks as $blockIndex => $blockData) {
                    $existingBlock = $floor->blocks->firstWhere('uid', $blockData['id']);
                    $block = $existingBlock ?: new GudangProdukLayoutBlock([
                        'uid' => $blockData['id'],
                        'floor_id' => $floor->id,
                    ]);

                    $canvasColumns = $this->clampInt(
                        $blockData['layoutCanvas']['columns'] ?? null,
                        6,
                        24,
                        self::DEFAULT_CANVAS_COLUMNS
                    );
                    $canvasRows = $this->clampInt(
                        $blockData['layoutCanvas']['rows'] ?? null,
                        4,
                        18,
                        self::DEFAULT_CANVAS_ROWS
                    );

                    $block->fill([
                        'floor_id' => $floor->id,
                        'code' => strtoupper((string) $blockData['code']),
                        'label' => $blockData['label'] ?? ('Blok ' . strtoupper((string) $blockData['code'])),
                        'layout_columns' => $this->clampInt(
                            $blockData['layoutColumns'] ?? null,
                            1,
                            4,
                            3
                        ),
                        'layout_canvas_columns' => $canvasColumns,
                        'layout_canvas_rows' => $canvasRows,
                        'sort_order' => $blockIndex,
                    ]);
                    $block->save();

                    $block->load('racks');

                    $incomingRacks = collect($blockData['racks'] ?? []);
                    $incomingRackUids = $incomingRacks->pluck('id')->filter()->values()->all();
                    if (count($incomingRackUids) > 0) {
                        $block->racks()->whereNotIn('uid', $incomingRackUids)->delete();
                    } else {
                        $block->racks()->delete();
                    }

                    foreach ($incomingRacks as $rackIndex => $rackData) {
                        $existingRack = $block->racks->firstWhere('uid', $rackData['id']);
                        $rack = $existingRack ?: new GudangProdukLayoutRack([
                            'uid' => $rackData['id'],
                            'block_id' => $block->id,
                        ]);

                        $rackPosition = $this->normalizeRackLayoutPosition(
                            $rackData['layoutPosition'] ?? null,
                            $canvasColumns,
                            $canvasRows
                        );

                        $rack->fill([
                            'block_id' => $block->id,
                            'number' => (int) $rackData['number'],
                            'rows' => (int) $rackData['rows'],
                            'label' => $rackData['label'] ?? ('Rak ' . str_pad((string) $rackData['number'], 2, '0', STR_PAD_LEFT)),
                            'position_x' => $rackPosition['x'],
                            'position_y' => $rackPosition['y'],
                            'width_cells' => $rackPosition['w'],
                            'height_cells' => $rackPosition['h'],
                            'sort_order' => $rackIndex,
                        ]);
                        $rack->save();
                    }
                }
            }

            $validSlotIds = $this->buildSlotIdsFromPayload($payload['id'], $payload['floors'] ?? []);

            $layout->slotAliases()->delete();
            foreach (($payload['slotAliases'] ?? []) as $slotId => $alias) {
                $trimmedAlias = trim((string) $alias);
                if ($trimmedAlias === '' || !in_array($slotId, $validSlotIds, true)) {
                    continue;
                }

                GudangProdukSlotAlias::create([
                    'layout_id' => $layout->id,
                    'slot_id' => $slotId,
                    'alias' => $trimmedAlias,
                ]);
            }

            if (count($validSlotIds) > 0) {
                $layout->stockEntries()->whereNotIn('slot_id', $validSlotIds)->delete();
            } else {
                $layout->stockEntries()->delete();
            }

            return $layout;
        });
    }

    private function normalizeRackLayoutPosition(?array $position, int $canvasColumns, int $canvasRows): array
    {
        $width = $this->clampInt($position['w'] ?? null, 2, $canvasColumns, min(4, $canvasColumns));
        $height = $this->clampInt($position['h'] ?? null, 2, $canvasRows, min(3, $canvasRows));

        return [
            'x' => $this->clampInt(
                $position['x'] ?? null,
                1,
                max($canvasColumns - $width + 1, 1),
                1
            ),
            'y' => $this->clampInt(
                $position['y'] ?? null,
                1,
                max($canvasRows - $height + 1, 1),
                1
            ),
            'w' => $width,
            'h' => $height,
        ];
    }

    private function buildWorkspaceSnapshot(): array
    {
        if (!$this->hasWorkspaceTables()) {
            return array_merge([
                'layouts' => [],
                'stockEntries' => [],
                'activityLog' => [],
            ], $this->buildCatalogSnapshot());
        }

        $layouts = GudangProdukLayout::with([
            'floors.blocks.racks',
            'slotAliases',
        ])
            ->orderBy('created_at')
            ->get()
            ->map(fn (GudangProdukLayout $layout) => $this->transformLayout($layout))
            ->values()
            ->all();

        $stockEntries = GudangProdukWorkspaceStockEntry::query()
            ->with('layout:id,uid')
            ->where('qty', '>', 0)
            ->orderByDesc('updated_at')
            ->get(['id', 'layout_id', 'slot_id', 'sku_id', 'qty', 'updated_at'])
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'layoutId' => optional($entry->layout)->uid,
                    'slotId' => $entry->slot_id,
                    'skuId' => $entry->sku_id,
                    'qty' => $entry->qty,
                    'updatedAt' => optional($entry->updated_at)->toISOString(),
                ];
            })
            ->values()
            ->all();

        $activityLog = GudangProdukActivityLog::query()
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['id', 'type', 'sku_id', 'from_slot_id', 'to_slot_id', 'qty', 'notes', 'created_at'])
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'skuId' => $activity->sku_id,
                    'fromSlotId' => $activity->from_slot_id,
                    'toSlotId' => $activity->to_slot_id,
                    'qty' => $activity->qty,
                    'notes' => $activity->notes,
                    'createdAt' => optional($activity->created_at)->toISOString(),
                ];
            })
            ->values()
            ->all();

        return array_merge([
            'layouts' => $layouts,
            'stockEntries' => $stockEntries,
            'activityLog' => $activityLog,
        ], $this->buildCatalogSnapshot());
    }

    private function buildCatalogSnapshot(): array
    {
        $products = Produk::query()
            ->orderBy('nama_produk')
            ->get(['id', 'nama_produk'])
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->nama_produk,
                ];
            })
            ->values()
            ->all();

        $skuModels = Sku::query()
            ->where('is_active', true)
            ->orderBy('sku')
            ->get(['id', 'sku']);

        $produkSkuBySku = ProdukSku::with('produk:id,nama_produk')
            ->whereIn('sku', $skuModels->pluck('sku')->values())
            ->get()
            ->keyBy('sku');

        $skus = $skuModels->map(function ($sku) use ($produkSkuBySku) {
            $produkSku = $produkSkuBySku->get($sku->sku);
            $product = $produkSku?->produk;
            $warna = strtoupper((string) ($produkSku->warna ?? ''));
            $ukuran = strtoupper((string) ($produkSku->ukuran ?? ''));
            $label = trim(
                implode(' - ', array_filter([
                    $product?->nama_produk,
                    trim($warna . ' ' . $ukuran),
                ]))
            );

            return [
                'id' => $sku->id,
                'productId' => $produkSku?->produk_id,
                'code' => $sku->sku,
                'label' => $label !== '' ? $label : $sku->sku,
            ];
        })->values()->all();

        return [
            'products' => $products,
            'skus' => $skus,
        ];
    }

    private function hasWorkspaceTables(): bool
    {
        return Schema::hasTable('gudang_produk_layouts')
            && Schema::hasTable('gudang_produk_layout_floors')
            && Schema::hasTable('gudang_produk_layout_blocks')
            && Schema::hasTable('gudang_produk_layout_racks')
            && Schema::hasTable('gudang_produk_slot_aliases')
            && Schema::hasTable('gudang_produk_stock_entries')
            && Schema::hasTable('gudang_produk_activity_logs');
    }

    private function ensureWorkspaceTablesReady(): void
    {
        if ($this->hasWorkspaceTables()) {
            return;
        }

        throw ValidationException::withMessages([
            'workspace' => ['Tabel Gudang Produk workspace belum siap. Jalankan migrasi backend terlebih dahulu.'],
        ]);
    }

    private function transformLayout(GudangProdukLayout $layout): array
    {
        $slotAliases = $layout->slotAliases
            ->mapWithKeys(function ($alias) {
                return [$alias->slot_id => $alias->alias];
            })
            ->all();

        $floors = $layout->floors
            ->sortBy(function ($floor) {
                return sprintf('%05d_%05d', (int) $floor->sort_order, (int) $floor->number);
            })
            ->values()
            ->map(function ($floor) {
                return [
                    'id' => $floor->uid,
                    'number' => (int) $floor->number,
                    'label' => $floor->label,
                    'blocks' => $floor->blocks
                        ->sortBy(function ($block) {
                            return sprintf('%05d_%s', (int) $block->sort_order, strtoupper((string) $block->code));
                        })
                        ->values()
                        ->map(function ($block) {
                            return [
                                'id' => $block->uid,
                                'code' => $block->code,
                                'label' => $block->label,
                                'layoutColumns' => (int) $block->layout_columns,
                                'layoutCanvas' => [
                                    'columns' => (int) $block->layout_canvas_columns,
                                    'rows' => (int) $block->layout_canvas_rows,
                                ],
                                'racks' => $block->racks
                                    ->sortBy(function ($rack) {
                                        return sprintf('%05d_%05d', (int) $rack->sort_order, (int) $rack->number);
                                    })
                                    ->values()
                                    ->map(function ($rack) {
                                        return [
                                            'id' => $rack->uid,
                                            'number' => (int) $rack->number,
                                            'rows' => (int) $rack->rows,
                                            'label' => $rack->label,
                                            'layoutPosition' => [
                                                'x' => (int) ($rack->position_x ?: 1),
                                                'y' => (int) ($rack->position_y ?: 1),
                                                'w' => (int) ($rack->width_cells ?: 2),
                                                'h' => (int) ($rack->height_cells ?: 2),
                                            ],
                                        ];
                                    })
                                    ->all(),
                            ];
                        })
                        ->all(),
                ];
            })
            ->all();

        return [
            'id' => $layout->uid,
            'name' => $layout->name,
            'address' => $layout->address,
            'pic' => $layout->pic,
            'description' => $layout->description,
            'slotAliases' => $slotAliases,
            'floors' => $floors,
        ];
    }

    private function buildSlotIdsFromPayload(string $layoutUid, array $floors): array
    {
        $slotIds = [];

        foreach ($floors as $floor) {
            foreach ($floor['blocks'] ?? [] as $block) {
                foreach ($block['racks'] ?? [] as $rack) {
                    for ($row = 1; $row <= (int) $rack['rows']; $row++) {
                        $slotIds[] = $this->generateSlotId(
                            $layoutUid,
                            (int) $floor['number'],
                            (string) $block['code'],
                            (int) $rack['number'],
                            $row
                        );
                    }
                }
            }
        }

        return $slotIds;
    }

    private function buildSlotIdsFromLayoutModel(GudangProdukLayout $layout): array
    {
        $slotIds = [];

        $layout->loadMissing(['floors.blocks.racks']);

        foreach ($layout->floors as $floor) {
            foreach ($floor->blocks as $block) {
                foreach ($block->racks as $rack) {
                    for ($row = 1; $row <= (int) $rack->rows; $row++) {
                        $slotIds[] = $this->generateSlotId(
                            $layout->uid,
                            (int) $floor->number,
                            (string) $block->code,
                            (int) $rack->number,
                            $row
                        );
                    }
                }
            }
        }

        return $slotIds;
    }

    private function findLayoutBySlotId(string $slotId): ?GudangProdukLayout
    {
        $layoutUid = explode('__', $slotId)[0] ?? null;
        if (!$layoutUid) {
            return null;
        }

        return GudangProdukLayout::with(['floors.blocks.racks'])
            ->where('uid', $layoutUid)
            ->first();
    }

    private function generateSlotId(
        string $layoutUid,
        int $floorNumber,
        string $blockCode,
        int $rackNumber,
        int $rowNumber
    ): string {
        return sprintf(
            '%s__F%s__B%s__R%s__ROW%s',
            $layoutUid,
            $floorNumber,
            strtoupper($blockCode),
            $rackNumber,
            $rowNumber
        );
    }

    private function clampInt($value, int $min, int $max, int $fallback): int
    {
        if (!is_numeric($value)) {
            return $fallback;
        }

        return max($min, min($max, (int) $value));
    }
}
