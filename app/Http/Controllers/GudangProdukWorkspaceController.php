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
use PhpOffice\PhpSpreadsheet\IOFactory;

class GudangProdukWorkspaceController extends Controller
{
    private const DEFAULT_CANVAS_COLUMNS = 12;
    private const DEFAULT_CANVAS_ROWS = 10;
    private const MAX_CANVAS_COLUMNS = 30;
    private const MAX_CANVAS_ROWS = 30;
    private const MAX_AUTO_GRID_COLUMNS = 20;

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

        // Cek rule: 1 rak bisa diisi banyak sku, 1 sku tidak bisa di banyak rak
        $existingPlacement = GudangProdukWorkspaceStockEntry::where('sku_id', $validated['skuId'])
            ->where('qty', '>', 0)
            ->first();

        if ($existingPlacement && $existingPlacement->slot_id !== $validated['slotId']) {
            throw ValidationException::withMessages([
                'slotId' => ['1 SKU tidak bisa disimpan di banyak rak/slot. SKU ini sudah ada di lokasi lain.'],
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

            // Cek rule: 1 SKU tidak bisa di banyak rak
            // Maka mutasi ke slot yang beda harus memindahkan seluruh stok, tidak boleh parsial
            if ($sourceEntry->qty > (int) $validated['qty'] && $validated['fromSlotId'] !== $validated['toSlotId']) {
                throw ValidationException::withMessages([
                    'qty' => ['Mutasi harus memindahkan seluruh stok sekaligus agar 1 SKU tidak tersebar di banyak rak.'],
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

    public function importStock(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'layoutId' => 'required|string|max:255',
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:204800',
            'replaceExistingStock' => 'nullable|boolean',
        ]);
        $replaceExistingStock = $request->boolean('replaceExistingStock');

        $layout = GudangProdukLayout::with(['floors.blocks.racks', 'slotAliases'])
            ->where('uid', $validated['layoutId'])
            ->firstOrFail();

        $file = $request->file('file');
        $fileName = $file?->getClientOriginalName() ?? 'import.xlsx';

        try {
            $rows = $this->readSpreadsheetRows((string) $file->getRealPath());
        } catch (\Throwable $throwable) {
            return response()->json([
                'message' => 'File Excel tidak bisa dibaca.',
                'errors' => [],
            ], 422);
        }

        $headerInfo = $this->detectStockImportHeader($rows);
        if ($headerInfo === null) {
            return response()->json([
                'message' => 'Header Excel tidak ditemukan. Pastikan file memiliki kolom sku_name, qty, dan mapping.',
                'errors' => [],
            ], 422);
        }

        [$headerRowIndex, $columnMap] = $headerInfo;
        $skuLookup = $this->buildStockImportSkuLookup();
        $skuMasterLookup = $this->buildStockImportSkuMasterLookup();
        $slotLookup = $this->buildStockImportSlotLookup($layout);

        $parsedRows = [];
        $errors = [];
        $seenSkuTargets = [];
        $totalRows = 0;
        $skippedRows = 0;

        for ($rowIndex = $headerRowIndex + 1; $rowIndex < count($rows); $rowIndex++) {
            $row = $rows[$rowIndex] ?? [];

            if ($this->isStockImportRowEmpty($row)) {
                continue;
            }

            $excelRowNumber = $rowIndex + 1;
            $rawSkuName = $this->getStockImportCellValue($row, $columnMap['sku']);
            $rawQty = $this->getStockImportCellValue($row, $columnMap['qty']);
            $rawMapping = $this->getStockImportCellValue($row, $columnMap['mapping']);

            if ($this->isStockImportPlaceholderRow($rawSkuName, $rawQty, $rawMapping)) {
                $skippedRows++;
                continue;
            }

            $totalRows++;

            if (trim((string) $rawSkuName) === '') {
                $errors[] = [
                    'row' => $excelRowNumber,
                    'message' => 'sku_name wajib diisi.',
                ];
                continue;
            }

            if (trim((string) $rawMapping) === '') {
                $errors[] = [
                    'row' => $excelRowNumber,
                    'message' => 'mapping wajib diisi.',
                ];
                continue;
            }

            $skuLookupKey = $this->normalizeStockImportSkuLookupValue($rawSkuName);
            $skuName = $this->normalizeStockImportSkuName($rawSkuName);

            $skuCandidates = $skuLookup[$skuLookupKey] ?? [];
            if (count($skuCandidates) > 1) {
                $errors[] = [
                    'row' => $excelRowNumber,
                    'message' => sprintf('SKU "%s" cocok ke lebih dari satu data aktif.', $rawSkuName),
                ];
                continue;
            }

            $skuCandidate = $skuCandidates[0] ?? null;
            if (!$skuCandidate) {
                $skuCandidates = $skuMasterLookup[$skuLookupKey] ?? [];
                if (count($skuCandidates) > 1) {
                    $errors[] = [
                        'row' => $excelRowNumber,
                        'message' => sprintf('SKU "%s" cocok ke lebih dari satu data master.', $rawSkuName),
                    ];
                    continue;
                }

                $skuCandidate = $skuCandidates[0] ?? null;
            }

            $skuId = (int) ($skuCandidate['id'] ?? 0);
            if ($skuCandidate && $skuId <= 0) {
                $errors[] = [
                    'row' => $excelRowNumber,
                    'message' => sprintf('SKU "%s" tidak valid.', $rawSkuName),
                ];
                continue;
            }

            $qty = $this->parseStockImportQty($rawQty);
            if ($qty === null) {
                $errors[] = [
                    'row' => $excelRowNumber,
                    'message' => 'Qty harus berupa angka bulat lebih dari 0.',
                ];
                continue;
            }

            $slotCandidates = $slotLookup[$this->normalizeImportLookupValue($rawMapping)] ?? [];
            if (!count($slotCandidates)) {
                $errors[] = [
                    'row' => $excelRowNumber,
                    'message' => sprintf('Mapping "%s" tidak ditemukan pada layout terpilih.', $rawMapping),
                ];
                continue;
            }

            if (count($slotCandidates) > 1) {
                $errors[] = [
                    'row' => $excelRowNumber,
                    'message' => sprintf('Mapping "%s" cocok ke lebih dari satu slot.', $rawMapping),
                ];
                continue;
            }

            $slot = $slotCandidates[0];
            $slotId = (string) ($slot['slotId'] ?? '');
            if ($slotId === '') {
                $errors[] = [
                    'row' => $excelRowNumber,
                    'message' => sprintf('Mapping "%s" tidak valid.', $rawMapping),
                ];
                continue;
            }

            if ($skuId > 0 && !$replaceExistingStock) {
                $existingPlacement = GudangProdukWorkspaceStockEntry::where('sku_id', $skuId)
                    ->where('qty', '>', 0)
                    ->first();

                if ($existingPlacement && $existingPlacement->slot_id !== $slotId) {
                    $errors[] = [
                        'row' => $excelRowNumber,
                        'message' => sprintf(
                            'SKU sudah tersimpan di %s.',
                            $this->resolveStockImportSlotLabel($slotLookup, $existingPlacement->slot_id)
                        ),
                    ];
                    continue;
                }
            }

            $skuKey = $skuId > 0 ? 'sku:' . $skuId : 'name:' . $skuLookupKey;
            if (isset($seenSkuTargets[$skuKey]) && $seenSkuTargets[$skuKey] !== $slotId) {
                $errors[] = [
                    'row' => $excelRowNumber,
                    'message' => 'SKU yang sama tidak boleh diarahkan ke slot berbeda di file yang sama.',
                ];
                continue;
            }

            $seenSkuTargets[$skuKey] = $slotId;

            $parsedRows[] = [
                'rowNumber' => $excelRowNumber,
                'skuId' => $skuId > 0 ? $skuId : null,
                'skuName' => $skuName,
                'skuLookupKey' => $skuLookupKey,
                'slotId' => $slotId,
                'layoutId' => $layout->id,
                'qty' => $qty,
                'notes' => sprintf('Import Excel: %s row %d', $fileName, $excelRowNumber),
            ];
        }

        if (!$totalRows) {
            return response()->json([
                'message' => 'File Excel tidak memiliki data untuk diimport.',
                'errors' => [],
                'skipped_rows' => $skippedRows,
            ], 422);
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'File Excel masih memiliki data yang tidak valid.',
                'errors' => $errors,
                'processed' => count($parsedRows),
                'total_rows' => $totalRows,
                'skipped_rows' => $skippedRows,
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $createdSkus = 0;
        $activatedSkus = 0;
        $replacedStockEntries = 0;
        $replacedStockQty = 0;

        DB::transaction(function () use (
            $layout,
            $parsedRows,
            $replaceExistingStock,
            &$created,
            &$updated,
            &$createdSkus,
            &$activatedSkus,
            &$replacedStockEntries,
            &$replacedStockQty
        ) {
            $skuMasterLookup = $this->buildStockImportSkuMasterLookup();
            $resolvedSkuCache = [];

            foreach ($parsedRows as $row) {
                $sku = $this->resolveStockImportSkuModel(
                    (string) $row['skuName'],
                    (string) $row['skuLookupKey'],
                    $row['skuId'] ?? null,
                    $skuMasterLookup,
                    $resolvedSkuCache,
                    $createdSkus,
                    $activatedSkus
                );

                if ($replaceExistingStock) {
                    $oldEntries = GudangProdukWorkspaceStockEntry::where('sku_id', $sku->id)
                        ->where('qty', '>', 0)
                        ->where('slot_id', '<>', $row['slotId'])
                        ->lockForUpdate()
                        ->get();

                    foreach ($oldEntries as $oldEntry) {
                        $oldQty = (int) $oldEntry->qty;

                        GudangProdukActivityLog::create([
                            'type' => 'mutation',
                            'sku_id' => $sku->id,
                            'from_slot_id' => $oldEntry->slot_id,
                            'to_slot_id' => null,
                            'qty' => $oldQty,
                            'notes' => sprintf(
                                'Import Excel overwrite: stok lama dihapus sebelum %s',
                                $row['notes']
                            ),
                            'created_by' => auth()->id(),
                        ]);

                        $oldEntry->delete();
                        $replacedStockEntries++;
                        $replacedStockQty += $oldQty;
                    }
                }

                $entry = GudangProdukWorkspaceStockEntry::where('layout_id', $layout->id)
                    ->where('slot_id', $row['slotId'])
                    ->where('sku_id', $sku->id)
                    ->lockForUpdate()
                    ->first();

                if ($entry) {
                    $entry->qty = (int) $entry->qty + (int) $row['qty'];
                    $entry->updated_by = auth()->id();
                    $entry->save();
                    $updated++;
                } else {
                    GudangProdukWorkspaceStockEntry::create([
                        'layout_id' => $layout->id,
                        'slot_id' => $row['slotId'],
                        'sku_id' => $sku->id,
                        'qty' => $row['qty'],
                        'updated_by' => auth()->id(),
                    ]);
                    $created++;
                }

                GudangProdukActivityLog::create([
                    'type' => 'placement',
                    'sku_id' => $sku->id,
                    'from_slot_id' => null,
                    'to_slot_id' => $row['slotId'],
                    'qty' => $row['qty'],
                    'notes' => $row['notes'],
                    'created_by' => auth()->id(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Import stok gudang berhasil.',
            'data' => $this->buildWorkspaceSnapshot(),
            'processed' => count($parsedRows),
            'created' => $created,
            'updated' => $updated,
            'created_skus' => $createdSkus,
            'activated_skus' => $activatedSkus,
            'replaced_stock_entries' => $replacedStockEntries,
            'replaced_stock_qty' => $replacedStockQty,
            'failed' => 0,
            'errors' => [],
            'skipped_rows' => $skippedRows,
            'layoutId' => $layout->uid,
            'fileName' => $fileName,
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
            'floors.*.blocks.*.layoutColumns' => 'nullable|integer|min:1|max:20',
            'floors.*.blocks.*.layoutCanvas' => 'nullable|array',
            'floors.*.blocks.*.layoutCanvas.columns' => 'nullable|integer|min:6|max:30',
            'floors.*.blocks.*.layoutCanvas.rows' => 'nullable|integer|min:4|max:30',
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

    private function readSpreadsheetRows(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($filePath);

        try {
            return $spreadsheet->getSheet(0)->toArray(null, true, true, false);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function detectStockImportHeader(array $rows): ?array
    {
        $scanLimit = min(count($rows), 15);

        for ($rowIndex = 0; $rowIndex < $scanLimit; $rowIndex++) {
            $headers = $rows[$rowIndex] ?? [];
            $columnMap = [
                'sku' => $this->findImportColumnIndex($headers, [
                    'sku_name',
                    'sku name',
                    'sku',
                    'nama sku',
                    'nama_sku',
                ]),
                'qty' => $this->findImportColumnIndex($headers, [
                    'qty',
                    'quantity',
                    'jumlah',
                    'jumlah masuk',
                    'pcs',
                ]),
                'mapping' => $this->findImportColumnIndex($headers, [
                    'mapping',
                    'slot',
                    'kode slot',
                    'kode lokasi',
                    'lokasi',
                ]),
            ];

            if ($columnMap['sku'] >= 0 && $columnMap['qty'] >= 0 && $columnMap['mapping'] >= 0) {
                return [$rowIndex, $columnMap];
            }
        }

        return null;
    }

    private function findImportColumnIndex(array $headers, array $aliases): int
    {
        $normalizedAliases = array_map(
            fn ($alias) => $this->normalizeImportHeaderValue($alias),
            $aliases
        );

        foreach ($headers as $index => $header) {
            if (in_array($this->normalizeImportHeaderValue($header), $normalizedAliases, true)) {
                return $index;
            }
        }

        return -1;
    }

    private function normalizeImportHeaderValue($value): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', '', strtolower((string) $value));

        return $normalized ?: '';
    }

    private function normalizeImportLookupValue($value): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', '', strtoupper((string) $value));

        return $normalized ?: '';
    }

    private function normalizeStockImportSkuName($value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $normalized ?: '';
    }

    private function normalizeStockImportSkuLookupValue($value): string
    {
        $normalized = $this->normalizeImportLookupValue($value);
        if ($normalized !== '') {
            return $normalized;
        }

        $fallback = $this->normalizeStockImportSkuName($value);

        return $fallback !== '' ? strtoupper($fallback) : '';
    }

    private function appendStockImportLookupCandidate(array &$lookup, $rawValue, array $candidate): void
    {
        $key = $this->normalizeImportLookupValue($rawValue);
        if ($key === '') {
            return;
        }

        if (!isset($lookup[$key])) {
            $lookup[$key] = [];
        }

        $identity = (string) ($candidate['id'] ?? $candidate['slotId'] ?? '');
        foreach ($lookup[$key] as $existing) {
            $existingIdentity = (string) ($existing['id'] ?? $existing['slotId'] ?? '');
            if ($existingIdentity === $identity) {
                return;
            }
        }

        $lookup[$key][] = $candidate;
    }

    private function appendStockImportSkuLookupCandidate(array &$lookup, $rawValue, array $candidate): void
    {
        $key = $this->normalizeStockImportSkuLookupValue($rawValue);
        if ($key === '') {
            return;
        }

        if (!isset($lookup[$key])) {
            $lookup[$key] = [];
        }

        $identity = (string) ($candidate['id'] ?? '');
        foreach ($lookup[$key] as $existing) {
            $existingIdentity = (string) ($existing['id'] ?? '');
            if ($existingIdentity === $identity) {
                return;
            }
        }

        $lookup[$key][] = $candidate;
    }

    private function buildStockImportSkuLookup(): array
    {
        $lookup = [];
        $catalog = $this->buildCatalogSnapshot();

        foreach ($catalog['skus'] ?? [] as $sku) {
            $candidate = [
                'id' => (int) ($sku['id'] ?? 0),
                'code' => (string) ($sku['code'] ?? ''),
                'label' => (string) ($sku['label'] ?? ''),
            ];

            $this->appendStockImportLookupCandidate($lookup, $candidate['id'], $candidate);
            $this->appendStockImportLookupCandidate($lookup, $candidate['code'], $candidate);
            $this->appendStockImportLookupCandidate($lookup, $candidate['label'], $candidate);
        }

        return $lookup;
    }

    private function buildStockImportSkuMasterLookup(): array
    {
        $lookup = [];

        Sku::query()
            ->orderBy('sku')
            ->get(['id', 'sku', 'is_active'])
            ->each(function ($sku) use (&$lookup) {
                $candidate = [
                    'id' => (int) $sku->id,
                    'sku' => (string) $sku->sku,
                    'is_active' => (bool) $sku->is_active,
                ];

                $this->appendStockImportSkuLookupCandidate($lookup, $candidate['sku'], $candidate);
            });

        return $lookup;
    }

    private function buildStockImportSlotLookup(GudangProdukLayout $layout): array
    {
        $layout->loadMissing(['floors.blocks.racks', 'slotAliases']);
        $lookup = [];
        $slotAliases = $layout->slotAliases
            ->mapWithKeys(function ($alias) {
                return [$alias->slot_id => $alias->alias];
            })
            ->all();

        $floors = $layout->floors
            ->sortBy(function ($floor) {
                return sprintf('%05d_%05d', (int) $floor->sort_order, (int) $floor->number);
            })
            ->values();

        foreach ($floors as $floor) {
            $blocks = $floor->blocks
                ->sortBy(function ($block) {
                    return sprintf('%05d_%s', (int) $block->sort_order, strtoupper((string) $block->code));
                })
                ->values();

            foreach ($blocks as $block) {
                $racks = $block->racks
                    ->sortBy(function ($rack) {
                        return sprintf('%05d_%05d', (int) $rack->sort_order, (int) $rack->number);
                    })
                    ->values();

                foreach ($racks as $rack) {
                    for ($row = 1; $row <= (int) $rack->rows; $row++) {
                        $slotId = $this->generateSlotId(
                            $layout->uid,
                            (int) $floor->number,
                            (string) $block->code,
                            (int) $rack->number,
                            $row
                        );

                        $candidate = [
                            'slotId' => $slotId,
                            'slotCode' => $this->generateSlotCode(
                                (int) $floor->number,
                                (string) $block->code,
                                (int) $rack->number,
                                $row
                            ),
                            'layoutId' => $layout->uid,
                            'alias' => (string) ($slotAliases[$slotId] ?? ''),
                        ];

                        $this->appendStockImportLookupCandidate($lookup, $slotId, $candidate);
                        $this->appendStockImportLookupCandidate($lookup, $candidate['slotCode'], $candidate);

                        if ($candidate['alias'] !== '') {
                            $this->appendStockImportLookupCandidate($lookup, $candidate['alias'], $candidate);
                        }
                    }
                }
            }
        }

        return $lookup;
    }

    private function resolveStockImportSkuModel(
        string $rawSkuName,
        string $skuLookupKey,
        ?int $preferredSkuId,
        array &$skuMasterLookup,
        array &$resolvedSkuCache,
        int &$createdSkuCount,
        int &$activatedSkuCount
    ): Sku {
        if (isset($resolvedSkuCache[$skuLookupKey])) {
            return $resolvedSkuCache[$skuLookupKey];
        }

        if ($preferredSkuId) {
            $preferredSku = Sku::query()->lockForUpdate()->find($preferredSkuId);
            if ($preferredSku) {
                if (!$preferredSku->is_active) {
                    $preferredSku->is_active = true;
                    $preferredSku->save();
                    $activatedSkuCount++;
                }

                return $resolvedSkuCache[$skuLookupKey] = $preferredSku;
            }
        }

        $skuCandidates = $skuMasterLookup[$skuLookupKey] ?? [];
        if (count($skuCandidates) > 1) {
            throw ValidationException::withMessages([
                'sku_name' => [sprintf('SKU "%s" cocok ke lebih dari satu data master.', $rawSkuName)],
            ]);
        }

        if (count($skuCandidates) === 1) {
            $candidateId = (int) ($skuCandidates[0]['id'] ?? 0);
            if ($candidateId <= 0) {
                throw ValidationException::withMessages([
                    'sku_name' => [sprintf('SKU "%s" tidak valid.', $rawSkuName)],
                ]);
            }

            $candidateSku = Sku::query()->lockForUpdate()->find($candidateId);
            if ($candidateSku) {
                if (!$candidateSku->is_active) {
                    $candidateSku->is_active = true;
                    $candidateSku->save();
                    $activatedSkuCount++;
                }

                return $resolvedSkuCache[$skuLookupKey] = $candidateSku;
            }
        }

        $skuName = $this->normalizeStockImportSkuName($rawSkuName);
        if ($skuName === '') {
            throw ValidationException::withMessages([
                'sku_name' => ['sku_name wajib diisi.'],
            ]);
        }

        $newSku = Sku::create([
            'sku' => $skuName,
            'is_active' => true,
        ]);

        $createdSkuCount++;
        $skuMasterLookup[$skuLookupKey] = [[
            'id' => $newSku->id,
            'sku' => $newSku->sku,
            'is_active' => true,
        ]];

        return $resolvedSkuCache[$skuLookupKey] = $newSku;
    }

    private function resolveStockImportSlotLabel(array $slotLookup, string $slotId): string
    {
        foreach ($slotLookup as $candidates) {
            foreach ($candidates as $candidate) {
                if ((string) ($candidate['slotId'] ?? '') === (string) $slotId) {
                    return (string) ($candidate['slotCode'] ?? $slotId);
                }
            }
        }

        return $slotId;
    }

    private function isStockImportRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isStockImportPlaceholderRow($rawSkuName, $rawQty, $rawMapping): bool
    {
        return trim((string) $rawSkuName) !== ''
            && trim((string) $rawQty) === ''
            && trim((string) $rawMapping) === '';
    }

    private function getStockImportCellValue(array $row, int $index)
    {
        if ($index < 0) {
            return '';
        }

        return $row[$index] ?? '';
    }

    private function parseStockImportQty($value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $numericValue = (float) $value;
            if ($numericValue <= 0 || floor($numericValue) !== $numericValue) {
                return null;
            }

            return (int) $numericValue;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $compactText = preg_replace('/\s+/', '', $text);
        $isThousandsFormat = preg_match('/^\d{1,3}([.,]\d{3})+$/', $compactText) === 1;
        $normalizedText = $isThousandsFormat
            ? preg_replace('/[.,]/', '', $compactText)
            : str_replace(',', '.', $compactText);

        if (!is_numeric($normalizedText)) {
            return null;
        }

        $numericValue = (float) $normalizedText;
        if ($numericValue <= 0 || floor($numericValue) !== $numericValue) {
            return null;
        }

        return (int) $numericValue;
    }

    private function generateSlotCode(
        int $floorNumber,
        string $blockCode,
        int $rackNumber,
        int $rowNumber
    ): string {
        return sprintf(
            'L%s%s%s/%s',
            $floorNumber,
            strtoupper($blockCode),
            str_pad((string) $rackNumber, 2, '0', STR_PAD_LEFT),
            $rowNumber
        );
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
                        self::MAX_CANVAS_COLUMNS,
                        self::DEFAULT_CANVAS_COLUMNS
                    );
                    $canvasRows = $this->clampInt(
                        $blockData['layoutCanvas']['rows'] ?? null,
                        4,
                        self::MAX_CANVAS_ROWS,
                        self::DEFAULT_CANVAS_ROWS
                    );

                    $block->fill([
                        'floor_id' => $floor->id,
                        'code' => strtoupper((string) $blockData['code']),
                        'label' => $blockData['label'] ?? ('Blok ' . strtoupper((string) $blockData['code'])),
                        'layout_columns' => $this->clampInt(
                            $blockData['layoutColumns'] ?? null,
                            1,
                            $this->resolveMaxLayoutColumns($canvasColumns),
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

    private function resolveMaxLayoutColumns(int $canvasColumns): int
    {
        return max(
            1,
            min((int) floor($canvasColumns / 2), self::MAX_AUTO_GRID_COLUMNS)
        );
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
