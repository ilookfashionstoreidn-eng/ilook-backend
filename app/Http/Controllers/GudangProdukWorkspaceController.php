<?php

namespace App\Http\Controllers;

use App\Models\GudangProdukActivityLog;
use App\Models\GudangProdukLayout;
use App\Models\GudangProdukLayoutBlock;
use App\Models\GudangProdukLayoutFloor;
use App\Models\GudangProdukLayoutRack;
use App\Models\GudangProdukMutationSession;
use App\Models\GudangProdukPlacementSession;
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
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GudangProdukWorkspaceController extends Controller
{
    private const DEFAULT_CANVAS_COLUMNS = 12;
    private const DEFAULT_CANVAS_ROWS = 10;
    private const MAX_CANVAS_COLUMNS = 70;
    private const MAX_CANVAS_ROWS = 70;
    private const MAX_AUTO_GRID_COLUMNS = 20;
    private const CANCELLED_SERI_PRINTS_TABLE = 'gudang_produk_cancelled_seri_prints';

    private static $tablesReady = null;
    private static $cancelledTableReady = null;

    public function index(Request $request)
    {
        if ($request->query('only') === 'catalog') {
            return response()->json([
                'data' => $this->buildCatalogSnapshot(),
            ]);
        }

        return response()->json([
            'data' => $this->buildWorkspaceSnapshot([
                'includeCatalog' => !$request->boolean('without_catalog'),
                'activityLimit' => $request->query('activity_limit', 500),
            ]),
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



        $entry = null;
        $activity = null;

        DB::transaction(function () use ($layout, $validated, &$entry, &$activity) {
            $entry = GudangProdukWorkspaceStockEntry::firstOrNew([
                'layout_id' => $layout->id,
                'slot_id' => $validated['slotId'],
                'sku_id' => $validated['skuId'],
            ]);

            $entry->qty = (int) ($entry->qty ?? 0) + (int) $validated['qty'];
            $entry->updated_by = auth()->id();
            $entry->save();

            $activity = GudangProdukActivityLog::create([
                'type' => 'placement',
                'sku_id' => $validated['skuId'],
                'from_slot_id' => null,
                'to_slot_id' => $validated['slotId'],
                'qty' => $validated['qty'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });

        if ($request->boolean('minimal')) {
            return response()->json([
                'message' => 'Placement gudang berhasil disimpan.',
                'data' => [
                    'stockEntry' => [
                        'id' => $entry?->id,
                        'layoutId' => $layout->uid,
                        'slotId' => $validated['slotId'],
                        'skuId' => $validated['skuId'],
                        'qty' => (int) ($entry?->qty ?? 0),
                        'updatedAt' => optional($entry?->updated_at)->toISOString(),
                    ],
                    'activity' => [
                        'id' => $activity?->id,
                        'type' => 'placement',
                        'skuId' => $validated['skuId'],
                        'fromSlotId' => null,
                        'toSlotId' => $validated['slotId'],
                        'qty' => $validated['qty'],
                        'notes' => $validated['notes'] ?? null,
                        'createdAt' => optional($activity?->created_at)->toISOString(),
                    ],
                ],
            ]);
        }

        return response()->json([
            'message' => 'Placement gudang berhasil disimpan.',
            'data' => $this->buildWorkspaceSnapshot(),
        ]);
    }

    public function downloadSerialBarcodes(Request $request)
    {
        $validated = $request->validate([
            'serial_base' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|string|max:255',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.start_number' => 'nullable|integer|min:1',
        ]);

        $serialBase = strtoupper(trim($validated['serial_base']));
        $labels = [];
        $nextNumber = 1;

        foreach ($validated['items'] as $item) {
            $sku = strtoupper(trim($item['sku']));
            $qty = max(1, (int) $item['qty']);
            $startNumber = isset($item['start_number'])
                ? max(1, (int) $item['start_number'])
                : $nextNumber;

            for ($index = 0; $index < $qty; $index++) {
                $serialNumber = $serialBase . '.' . ($startNumber + $index);
                $qrContent = strtoupper($sku . ' | ' . $serialNumber);
                $qr = QrCode::format('svg')->size(300)->generate($qrContent);

                $labels[] = [
                    'sku' => $sku,
                    'nomor_seri' => strtoupper($serialNumber),
                    'qr' => base64_encode($qr),
                ];
            }

            $nextNumber = max($nextNumber, $startNumber + $qty);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.qr_seri', [
            'labels' => $labels,
        ]);

        $pdf->setPaper([0, 0, 141.7, 141.7]);

        return $pdf->download('qr-seri-' . Str::slug($serialBase) . '.pdf');
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
            'data' => $this->buildWorkspaceSnapshot([
                'includeCatalog' => false,
                'activityLimit' => 20,
            ]),
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

    // ─── Mutation Sessions ────────────────────────────────────────────────────

    public function getMutationSessions(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $sessions = GudangProdukMutationSession::where('status', 'pending')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($session) {
                return [
                    'id'          => $session->id,
                    'layoutId'    => $session->layout_id,
                    'fromSlotId'  => $session->from_slot_id,
                    'skuId'       => $session->sku_id,
                    'barcodes'    => $session->barcodes ?? [],
                    'notes'       => $session->notes,
                    'status'      => $session->status,
                    'createdBy'   => $session->created_by,
                    'createdAt'   => $session->created_at,
                ];
            });

        return response()->json([
            'data' => $sessions,
        ]);
    }

    public function storeMutationSession(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'fromSlotId'  => 'required|string|max:255',
            'skuId'       => 'required|integer|exists:skus,id',
            'barcodes'    => 'required|array|min:1',
            'barcodes.*.key'        => 'required|string',
            'barcodes.*.barcode'    => 'required|string',
            'barcodes.*.skuCode'    => 'required|string',
            'barcodes.*.serialCode' => 'required|string',
            'notes'       => 'nullable|string',
        ]);

        // Derive layout from fromSlotId (uid prefix)
        $sourceLayout = $this->findLayoutBySlotId($validated['fromSlotId']);
        if (!$sourceLayout) {
            throw ValidationException::withMessages([
                'fromSlotId' => ['Slot asal tidak ditemukan pada layout manapun.'],
            ]);
        }

        $session = GudangProdukMutationSession::create([
            'layout_id'    => $sourceLayout->id,
            'from_slot_id' => $validated['fromSlotId'],
            'sku_id'       => $validated['skuId'],
            'barcodes'     => $validated['barcodes'],
            'notes'        => $validated['notes'] ?? null,
            'status'       => 'pending',
            'created_by'   => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Sesi scan berhasil disimpan.',
            'data'    => [
                'id'         => $session->id,
                'fromSlotId' => $session->from_slot_id,
                'skuId'      => $session->sku_id,
                'barcodes'   => $session->barcodes,
                'notes'      => $session->notes,
                'status'     => $session->status,
                'createdAt'  => $session->created_at,
            ],
        ], 201);
    }

    public function deleteMutationSession(Request $request, int $id)
    {
        $this->ensureWorkspaceTablesReady();

        $session = GudangProdukMutationSession::where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        $session->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Sesi scan dibatalkan.',
        ]);
    }

    public function executeMutationSession(Request $request, int $id)
    {
        $this->ensureWorkspaceTablesReady();

        $session = GudangProdukMutationSession::where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        $validated = $request->validate([
            'toSlotId' => 'required|string|max:255',
            'notes'    => 'nullable|string',
        ]);

        if ($validated['toSlotId'] === $session->from_slot_id) {
            throw ValidationException::withMessages([
                'toSlotId' => ['Slot tujuan harus berbeda dari slot asal.'],
            ]);
        }

        $sourceLayout = $this->findLayoutBySlotId($session->from_slot_id);
        $targetLayout = $this->findLayoutBySlotId($validated['toSlotId']);

        if (!$sourceLayout || !$targetLayout) {
            throw ValidationException::withMessages([
                'slot' => ['Slot asal atau tujuan tidak valid.'],
            ]);
        }

        $qty = count($session->barcodes ?? []);
        $barcodes = $session->barcodes ?? [];
        $serialCodes = array_map(fn($b) => $b['serialCode'] ?? $b['barcode'], $barcodes);

        $notesText = implode(' | ', array_filter([
            $validated['notes'] ?? $session->notes,
            'Barcode: ' . implode(', ', $serialCodes),
            'Sesi: ' . $session->id,
        ]));

        DB::transaction(function () use ($session, $validated, $sourceLayout, $targetLayout, $qty, $notesText) {
            $sourceEntries = GudangProdukWorkspaceStockEntry::where('layout_id', $sourceLayout->id)
                ->where('slot_id', $session->from_slot_id)
                ->where('sku_id', $session->sku_id)
                ->lockForUpdate()
                ->get();

            $totalAvailable = $sourceEntries->sum('qty');

            // --- BYPASS PENGECEKAN STOK ---
            // Jika Anda men-scan 46 pcs, tapi secara sistem di rak asal cuma tercatat 10 pcs, 
            // sistem akan mengosongkan (0) rak asal, dan memindahkan 46 pcs ke rak tujuan.
            // Hal ini karena kita memprioritaskan "kenyataan fisik (scan barcode)" daripada "data sistem".
            // 
            // if ($totalAvailable < $qty) {
            //     throw ValidationException::withMessages([
            //         'qty' => ['Stok di lokasi asal tidak mencukupi untuk mutasi. (Dibutuhkan: ' . $qty . ', Tersedia: ' . $totalAvailable . ')'],
            //     ]);
            // }

            $remainingToDeduct = $qty;
            foreach ($sourceEntries as $entry) {
                if ($remainingToDeduct <= 0) break;

                $deduct = min($entry->qty, $remainingToDeduct);
                $entry->qty -= $deduct;
                $entry->updated_by = auth()->id();
                
                if ($entry->qty <= 0) {
                    $entry->delete();
                } else {
                    $entry->save();
                }
                
                $remainingToDeduct -= $deduct;
            }

            $targetEntry = GudangProdukWorkspaceStockEntry::firstOrNew([
                'layout_id' => $targetLayout->id,
                'slot_id'   => $validated['toSlotId'],
                'sku_id'    => $session->sku_id,
            ]);
            $targetEntry->qty = (int) ($targetEntry->qty ?? 0) + $qty;
            $targetEntry->updated_by = auth()->id();
            $targetEntry->save();

            GudangProdukActivityLog::create([
                'type'         => 'mutation',
                'sku_id'       => $session->sku_id,
                'from_slot_id' => $session->from_slot_id,
                'to_slot_id'   => $validated['toSlotId'],
                'qty'          => $qty,
                'notes'        => $notesText,
                'created_by'   => auth()->id(),
            ]);

            $session->update(['status' => 'done']);
        });

        return response()->json([
            'message' => 'Mutasi dari sesi berhasil dieksekusi.',
            'data'    => $this->buildWorkspaceSnapshot(),
        ]);
    }

    public function revertMutationSession(Request $request, int $id)
    {
        $this->ensureWorkspaceTablesReady();

        $session = GudangProdukMutationSession::where('id', $id)
            ->where('status', 'done')
            ->firstOrFail();

        // Find the corresponding mutation activity log to know to_slot_id
        $log = GudangProdukActivityLog::where('type', 'mutation')
            ->where('sku_id', $session->sku_id)
            ->where('from_slot_id', $session->from_slot_id)
            ->where('notes', 'like', '%Sesi: ' . $session->id . '%')
            ->first();

        if (!$log) {
            throw ValidationException::withMessages([
                'session' => ['Log mutasi untuk sesi ini tidak ditemukan, tidak dapat dibatalkan.'],
            ]);
        }

        $toSlotId = $log->to_slot_id;
        $qty = count($session->barcodes ?? []);

        DB::transaction(function () use ($session, $log, $toSlotId, $qty) {
            $targetEntries = GudangProdukWorkspaceStockEntry::where('slot_id', $toSlotId)
                ->where('sku_id', $session->sku_id)
                ->lockForUpdate()
                ->get();

            $totalTargetAvailable = $targetEntries->sum('qty');

            if ($totalTargetAvailable < $qty) {
                throw ValidationException::withMessages([
                    'qty' => ['Stok di rak tujuan sudah dipindahkan (tersedia: ' . $totalTargetAvailable . ' pcs, butuh: ' . $qty . ' pcs). Tidak dapat membatalkan mutasi ini.'],
                ]);
            }

            // Deduct from target slot
            $remainingToDeduct = $qty;
            foreach ($targetEntries as $entry) {
                if ($remainingToDeduct <= 0) break;

                $deduct = min($entry->qty, $remainingToDeduct);
                $entry->qty -= $deduct;
                $entry->updated_by = auth()->id();
                
                if ($entry->qty <= 0) {
                    $entry->delete();
                } else {
                    $entry->save();
                }
                
                $remainingToDeduct -= $deduct;
            }

            // Return to source slot
            $sourceLayout = $this->findLayoutBySlotId($session->from_slot_id);
            if (!$sourceLayout) {
                throw ValidationException::withMessages([
                    'slot' => ['Slot asal sudah tidak valid.'],
                ]);
            }

            $sourceEntry = GudangProdukWorkspaceStockEntry::firstOrNew([
                'layout_id' => $sourceLayout->id,
                'slot_id'   => $session->from_slot_id,
                'sku_id'    => $session->sku_id,
            ]);
            $sourceEntry->qty = (int) ($sourceEntry->qty ?? 0) + $qty;
            $sourceEntry->updated_by = auth()->id();
            $sourceEntry->save();

            // Delete the mutation log so it disappears from history
            $log->delete();

            // Revert session status to pending
            $session->update(['status' => 'pending']);
        });

        return response()->json([
            'message' => 'Mutasi berhasil dibatalkan. Sesi kembali ke status pending.',
            'data'    => $this->buildWorkspaceSnapshot(),
        ]);
    }

    public function getPlacementSessions(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $this->autoFixMismatchedPendingSessions();

        $sessions = GudangProdukPlacementSession::with(['creator', 'sku', 'seri'])->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($session) {
                return [
                    'id'          => $session->id,
                    'seriId'      => $session->seri_id,
                    'seriNumber'  => $session->seri?->nomor_seri,
                    'skuId'       => $session->sku_id,
                    'skuCode'     => $session->sku?->sku,
                    'barcodes'    => $session->barcodes ?? [],
                    'notes'       => $session->notes,
                    'status'      => $session->status,
                    'createdBy'   => $session->created_by,
                    'creatorName' => $session->creator?->name ?? 'System',
                    'createdAt'   => $session->created_at,
                ];
            });

        return response()->json([
            'data' => $sessions,
        ]);
    }

    public function storePlacementSession(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'seriId'      => 'required|integer|exists:seri,id',
            'skuId'       => 'required|integer|exists:skus,id',
            'barcodes'    => 'required|array|min:1',
            'barcodes.*.key'        => 'required|string',
            'barcodes.*.barcode'    => 'required|string',
            'barcodes.*.skuCode'    => 'required|string',
            'barcodes.*.serialCode' => 'required|string',
            'notes'       => 'nullable|string',
        ]);

        $pendingBarcodes = [];
        $otherSessions = GudangProdukPlacementSession::where('status', 'pending')->get();
        foreach ($otherSessions as $otherSession) {
            $otherBarcodes = $otherSession->barcodes ?? [];
            foreach ($otherBarcodes as $b) {
                $serialCode = $b['serialCode'] ?? $b['barcode'] ?? null;
                if ($serialCode) {
                    $pendingBarcodes[trim($serialCode)] = $otherSession->id;
                }
            }
        }

        foreach ($validated['barcodes'] as $b) {
            $serialCode = $b['serialCode'] ?? $b['barcode'] ?? null;
            if ($serialCode) {
                $cleanSerialCode = trim($serialCode);
                if (isset($pendingBarcodes[$cleanSerialCode])) {
                    throw ValidationException::withMessages([
                        'barcodes' => ["Barcode \"{$cleanSerialCode}\" sudah terdaftar di sesi pending #{$pendingBarcodes[$cleanSerialCode]}."],
                    ]);
                }
            }
        }

        $session = GudangProdukPlacementSession::create([
            'seri_id'    => $validated['seriId'],
            'sku_id'     => $validated['skuId'],
            'barcodes'   => $validated['barcodes'],
            'notes'      => $validated['notes'] ?? null,
            'status'     => 'pending',
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Sesi scan masuk berhasil disimpan.',
            'data'    => [
                'id'        => $session->id,
                'seriId'    => $session->seri_id,
                'skuId'     => $session->sku_id,
                'barcodes'  => $session->barcodes,
                'notes'     => $session->notes,
                'status'    => $session->status,
                'createdAt' => $session->created_at,
            ],
        ], 201);
    }

    public function deletePlacementSession(Request $request, int $id)
    {
        $this->ensureWorkspaceTablesReady();

        $session = GudangProdukPlacementSession::where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        $session->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Sesi scan masuk dibatalkan.',
        ]);
    }

    public function executePlacementSession(Request $request, int $id)
    {
        $this->ensureWorkspaceTablesReady();

        $session = GudangProdukPlacementSession::where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        $validated = $request->validate([
            'layoutId' => 'required|string|max:255',
            'slotId'   => 'required|string|max:255',
            'notes'    => 'nullable|string',
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

        $seri = \App\Models\Seri::find($session->seri_id);
        if (!$seri) {
            throw ValidationException::withMessages([
                'session' => ['Nomor seri tidak ditemukan.'],
            ]);
        }

        $this->ensureCancelledSeriPrintsTableReady();
        $cancelledPrints = DB::table(self::CANCELLED_SERI_PRINTS_TABLE)
            ->where('seri_id', $seri->id)
            ->pluck('barcode_seri')
            ->all();

        $activities = GudangProdukActivityLog::where('type', 'placement')
            ->where('notes', 'like', '%Kode seri: ' . $seri->nomor_seri . '.%')
            ->get();

        $scannedBarcodesMap = [];
        foreach ($activities as $activity) {
            if (preg_match('/Kode seri:\s*([^\s,|]+)/i', $activity->notes, $matches)) {
                $barcodeKey = trim($matches[1]);
                $barcodeKey = rtrim($barcodeKey, '., ');
                $scannedBarcodesMap[$barcodeKey] = true;
            }
        }

        $qty = count($session->barcodes ?? []);
        $barcodes = $session->barcodes ?? [];

        foreach ($barcodes as $b) {
            $serialCode = $b['serialCode'] ?? $b['barcode'];
            $cleanSerialCode = trim($serialCode);
            if (in_array($cleanSerialCode, $cancelledPrints, true)) {
                throw ValidationException::withMessages([
                    'session' => ["Barcode \"{$cleanSerialCode}\" sudah dibatalkan dan tidak bisa dimasukkan ke gudang."],
                ]);
            }
            if (isset($scannedBarcodesMap[$cleanSerialCode])) {
                throw ValidationException::withMessages([
                    'session' => ["Barcode \"{$cleanSerialCode}\" sudah pernah dimasukkan ke gudang sebelumnya."],
                ]);
            }
        }

        $entry = null;
        $placements = [];

        DB::transaction(function () use ($session, $validated, $layout, $qty, $seri, $barcodes, &$entry, &$placements) {
            $entry = GudangProdukWorkspaceStockEntry::firstOrNew([
                'layout_id' => $layout->id,
                'slot_id'   => $validated['slotId'],
                'sku_id'    => $session->sku_id,
            ]);

            $entry->qty = (int) ($entry->qty ?? 0) + $qty;
            $entry->updated_by = auth()->id();
            $entry->save();

            foreach ($barcodes as $b) {
                $serialCode = $b['serialCode'] ?? $b['barcode'];
                $cleanSerialCode = trim($serialCode);

                $kodeSeri = $seri->nomor_seri;
                $nomorSeri = null;
                if (strpos($cleanSerialCode, $kodeSeri . '.') === 0) {
                    $nomorSeri = substr($cleanSerialCode, strlen($kodeSeri) + 1);
                }

                $notes = 'Scan produk masuk';
                if ($kodeSeri) {
                    $notes .= " | Kode seri: {$kodeSeri}";
                }
                if ($nomorSeri) {
                    $notes .= ".{$nomorSeri}";
                }
                $notes .= " | Sesi masuk: " . $session->id;

                $activity = GudangProdukActivityLog::create([
                    'type'         => 'placement',
                    'sku_id'       => $session->sku_id,
                    'from_slot_id' => null,
                    'to_slot_id'   => $validated['slotId'],
                    'qty'          => 1,
                    'notes'        => $notes,
                    'created_by'   => auth()->id(),
                ]);

                $placements[] = [
                    'id' => $activity->id,
                    'serialCode' => $cleanSerialCode,
                ];
            }

            $session->update(['status' => 'done']);
        });

        return response()->json([
            'message' => 'Penempatan dari sesi berhasil dieksekusi.',
            'data'    => $this->buildWorkspaceSnapshot(),
        ]);
    }

    public function revertPlacementSession(Request $request, int $id)
    {
        $this->ensureWorkspaceTablesReady();

        $session = GudangProdukPlacementSession::where('id', $id)
            ->where('status', 'done')
            ->firstOrFail();

        // Find the corresponding placement activity logs for this session
        $logs = GudangProdukActivityLog::where('type', 'placement')
            ->where('sku_id', $session->sku_id)
            ->where('notes', 'like', '%Sesi masuk: ' . $session->id . '%')
            ->get();

        if ($logs->isEmpty()) {
            throw ValidationException::withMessages([
                'session' => ['Log penempatan untuk sesi ini tidak ditemukan, tidak dapat dibatalkan.'],
            ]);
        }

        $toSlotId = $logs->first()->to_slot_id;
        $qty = count($session->barcodes ?? []);

        DB::transaction(function () use ($session, $logs, $toSlotId, $qty) {
            $targetEntries = GudangProdukWorkspaceStockEntry::where('slot_id', $toSlotId)
                ->where('sku_id', $session->sku_id)
                ->lockForUpdate()
                ->get();

            $totalTargetAvailable = $targetEntries->sum('qty');

            if ($totalTargetAvailable < $qty) {
                throw ValidationException::withMessages([
                    'qty' => ['Stok di rak tujuan sudah dipindahkan (tersedia: ' . $totalTargetAvailable . ' pcs, butuh: ' . $qty . ' pcs). Tidak dapat membatalkan placement ini.'],
                ]);
            }

            // Deduct from target slot
            $remainingToDeduct = $qty;
            foreach ($targetEntries as $entry) {
                if ($remainingToDeduct <= 0) break;

                $deduct = min($entry->qty, $remainingToDeduct);
                $entry->qty -= $deduct;
                $entry->updated_by = auth()->id();
                
                if ($entry->qty <= 0) {
                    $entry->delete();
                } else {
                    $entry->save();
                }
                
                $remainingToDeduct -= $deduct;
            }

            // Delete the placement logs so they disappear from history
            foreach ($logs as $log) {
                $log->delete();
            }

            // Revert session status to pending
            $session->update(['status' => 'pending']);
        });

        return response()->json([
            'message' => 'Penempatan berhasil dibatalkan. Sesi kembali ke status pending.',
            'data'    => $this->buildWorkspaceSnapshot(),
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
            'floors.*.blocks.*.layoutCanvas.columns' => 'nullable|integer|min:6|max:70',
            'floors.*.blocks.*.layoutCanvas.rows' => 'nullable|integer|min:4|max:70',
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

    private function buildWorkspaceSnapshot(array $options = []): array
    {
        $includeCatalog = $options['includeCatalog'] ?? true;
        $activityLimit = max(0, min(500, (int) ($options['activityLimit'] ?? 500)));

        if (!$this->hasWorkspaceTables()) {
            $snapshot = [
                'layouts' => [],
                'stockEntries' => [],
                'activityLog' => [],
            ];

            return $includeCatalog ? array_merge($snapshot, $this->buildCatalogSnapshot()) : $snapshot;
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

        $activityLog = $activityLimit > 0
            ? GudangProdukActivityLog::query()
                ->with('creator')
                ->orderByDesc('created_at')
                ->limit($activityLimit)
                ->get(['id', 'type', 'sku_id', 'from_slot_id', 'to_slot_id', 'qty', 'notes', 'created_at', 'created_by'])
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
                        'createdBy' => $activity->created_by,
                        'creatorName' => $activity->creator?->name ?? 'System',
                    ];
                })
                ->values()
                ->all()
            : [];

        $snapshot = [
            'layouts' => $layouts,
            'stockEntries' => $stockEntries,
            'activityLog' => $activityLog,
        ];

        return $includeCatalog ? array_merge($snapshot, $this->buildCatalogSnapshot()) : $snapshot;
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
        if (self::$tablesReady !== null) {
            return self::$tablesReady;
        }

        self::$tablesReady = Schema::hasTable('gudang_produk_layouts')
            && Schema::hasTable('gudang_produk_layout_floors')
            && Schema::hasTable('gudang_produk_layout_blocks')
            && Schema::hasTable('gudang_produk_layout_racks')
            && Schema::hasTable('gudang_produk_slot_aliases')
            && Schema::hasTable('gudang_produk_stock_entries')
            && Schema::hasTable('gudang_produk_activity_logs');

        return self::$tablesReady;
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

    private function ensureCancelledSeriPrintsTableReady(): void
    {
        if (self::$cancelledTableReady) {
            return;
        }

        if (Schema::hasTable(self::CANCELLED_SERI_PRINTS_TABLE)) {
            self::$cancelledTableReady = true;
            return;
        }

        Schema::create(self::CANCELLED_SERI_PRINTS_TABLE, function ($table) {
            $table->id();
            $table->unsignedBigInteger('seri_id')->nullable()->index();
            $table->string('nomor_seri')->index();
            $table->unsignedInteger('print_seq')->index();
            $table->string('barcode_seri')->unique();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->timestamps();
        });

        self::$cancelledTableReady = true;
    }

    private function findPlacementActivityBySerial(string $barcode)
    {
        return GudangProdukActivityLog::where('type', 'placement')
            ->where(function ($query) use ($barcode) {
                $query->where('notes', 'like', '%Kode seri: ' . $barcode)
                    ->orWhere('notes', 'like', '%Kode seri: ' . $barcode . ' %')
                    ->orWhere('notes', 'like', '%Kode seri: ' . $barcode . ',%')
                    ->orWhere('notes', 'like', '%Kode seri: ' . $barcode . '|%');
            })
            ->orderBy('created_at', 'desc')
            ->first();
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

    /**
     * Scan barcode kode seri produk untuk masuk ke gudang.
     *
     * Barcode bisa berformat:
     * - "KODE_SERI" (misalnya "3100.112") → lookup via spk_cutting_distribusi
     * - "SKU | KODE_SERI.NOMOR" (misalnya "SKU-001 | 3100.112.1") → parse SKU + kode seri
     * - "SKU_CODE" langsung → lookup via tabel skus
     */
    private function resolveBarcodeDetails(string $barcode, string $layoutId, string $slotId)
    {
        // Validate layout exists
        $layout = GudangProdukLayout::with(['floors.blocks.racks'])
            ->where('uid', $layoutId)
            ->first();

        if (!$layout) {
            return [
                'success' => false,
                'message' => 'Gudang tidak ditemukan.',
            ];
        }

        // Validate slot exists in layout
        $validSlotIds = $this->buildSlotIdsFromLayoutModel($layout);
        if (!in_array($slotId, $validSlotIds, true)) {
            return [
                'success' => false,
                'message' => 'Slot tujuan tidak ditemukan pada gudang yang dipilih.',
            ];
        }

        // ── Parse barcode ──────────────────────────────────────────────
        $kodeSeri   = null;
        $nomorSeri  = null;
        $skuCode    = null;
        $produkName = null;
        $skuModel   = null;

        // Format 1: "SKU | KODE_SERI.NOMOR" (from serial barcode labels)
        if (str_contains($barcode, '|')) {
            $parts   = array_map('trim', explode('|', $barcode, 2));
            $skuCode = strtoupper($parts[0] ?? '');
            $serialPart = $parts[1] ?? '';

            // Parse kode_seri and nomor_seri from serial part (e.g. "3100.112.1")
            $lastDot = strrpos($serialPart, '.');
            if ($lastDot !== false) {
                $kodeSeri  = substr($serialPart, 0, $lastDot);
                $nomorSeri = substr($serialPart, $lastDot + 1);
            } else {
                $kodeSeri = $serialPart;
            }
        } else {
            // Format 2: Plain barcode — could be kode_seri or SKU code
            // Try to extract nomor_seri from end (e.g. "3100.112.1" → kode_seri = "3100.112", nomor = "1")
            $lastDot = strrpos($barcode, '.');
            if ($lastDot !== false) {
                $possibleNumber = substr($barcode, $lastDot + 1);
                $possibleBase   = substr($barcode, 0, $lastDot);
                if (is_numeric($possibleNumber) && $possibleBase !== '') {
                    $kodeSeri  = $possibleBase;
                    $nomorSeri = $possibleNumber;
                }
            }

            // If no kode_seri parsed, treat entire barcode as potential SKU or kode_seri
            if (!$kodeSeri) {
                $kodeSeri = $barcode;
            }
        }

        // ── Check if duplicate serial barcode scan ──────────────────────
        if ($kodeSeri && $nomorSeri) {
            $serialIdentifier = "{$kodeSeri}.{$nomorSeri}";
            $alreadyScanned = (bool) $this->findPlacementActivityBySerial($serialIdentifier);

            if ($alreadyScanned) {
                return [
                    'success' => false,
                    'message' => "Kode seri \"{$serialIdentifier}\" sudah pernah di-scan masuk sebelumnya.",
                ];
            }

            $this->ensureCancelledSeriPrintsTableReady();
            $isCancelled = DB::table(self::CANCELLED_SERI_PRINTS_TABLE)
                ->where('barcode_seri', $serialIdentifier)
                ->exists();

            if ($isCancelled) {
                return [
                    'success' => false,
                    'message' => "Kode seri \"{$serialIdentifier}\" sudah dibatalkan dan tidak bisa di-scan masuk.",
                ];
            }
        }

        $isSerialFormat = str_contains($barcode, '|') || ($nomorSeri !== null);

        // ── 1. Lookup via Seri table ───────────────────────────────
        $seriModel = null;
        if ($kodeSeri) {
            $seriModels = \App\Models\Seri::where('nomor_seri', $kodeSeri)->orderBy('id')->get();
            if ($seriModels->isNotEmpty()) {
                if ($nomorSeri !== null && is_numeric($nomorSeri)) {
                    $seq = (int) $nomorSeri;
                    $runningSum = 0;
                    foreach ($seriModels as $model) {
                        $start = $runningSum + 1;
                        $end = $runningSum + (int) $model->jumlah;
                        if ($seq >= $start && $seq <= $end) {
                            $seriModel = $model;
                            break;
                        }
                        $runningSum = $end;
                    }
                }
                if (!$seriModel) {
                    $seriModel = $seriModels->first();
                }
            }
        }

        if ($seriModel && !empty($seriModel->sku)) {
            $expectedSkuCode = trim($seriModel->sku);
            $skuCode = $expectedSkuCode;

            $skuModel = Sku::where('sku', $expectedSkuCode)->first();
            if (!$skuModel) {
                $skuModel = Sku::firstOrCreate(
                    ['sku' => $expectedSkuCode],
                    ['is_active' => true]
                );
            }

            // Resolve product name
            $productList = \App\Models\ProductList::where('sku_name', $skuCode)->first();
            if ($productList) {
                $produkName = $productList->product;
            } else {
                $produkSku = \App\Models\ProdukSku::with('produk')->where('sku', $skuCode)->first();
                if ($produkSku && $produkSku->produk) {
                    $produkName = $produkSku->produk->nama_produk;
                }
            }
        }

        // ── 2. Lookup via spk_cutting_distribusi if not resolved by Seri ──
        if (!$skuModel) {
            $distribusi = null;
            if ($kodeSeri) {
                $distribusi = \App\Models\SpkCuttingDistribusi::with(['spkCutting.produk'])
                    ->where('kode_seri', $kodeSeri)
                    ->first();
            }

            if ($distribusi) {
                $produkModel = $distribusi->spkCutting?->produk;
                $produkName  = $produkModel?->nama_produk ?? '-';

                // Find SKU from barcode or from produk's SKU
                if ($skuCode) {
                    $skuModel = Sku::where('sku', $skuCode)->first();
                }

                if (!$skuModel && $produkModel) {
                    // Try to find SKU associated with this produk
                    $produkSku = ProdukSku::where('produk_id', $produkModel->id)->first();
                    if ($produkSku) {
                        $skuModel = Sku::find($produkSku->sku_id);
                    }
                }

                if (!$skuModel && $skuCode && !$isSerialFormat) {
                    // Auto-create SKU if it doesn't exist yet (disabled for serial format)
                    $skuModel = Sku::firstOrCreate(
                        ['sku' => $skuCode],
                        ['is_active' => true]
                    );
                }
            }
        }

        // ── 3. Fallback: lookup barcode as SKU code directly ──────────
        if (!$skuModel) {
            if ($skuCode) {
                $skuModel = Sku::where('sku', $skuCode)->first();
            }

            if (!$skuModel) {
                $normalizedBarcode = strtoupper(trim(str_replace(['|', ' '], '', $barcode)));
                $skuModel = Sku::whereRaw('UPPER(REPLACE(sku, " ", "")) = ?', [$normalizedBarcode])->first();
            }

            if (!$skuModel) {
                // Also try the raw barcode
                $skuModel = Sku::where('sku', strtoupper(trim($barcode)))->first();
            }

            // Auto-create SKU if it still doesn't exist but we successfully parsed a SKU code
            // Only allow auto-creation if NOT in serial format
            if (!$skuModel && $skuCode && !$isSerialFormat) {
                $skuModel = Sku::firstOrCreate(
                    ['sku' => $skuCode],
                    ['is_active' => true]
                );
            }

            if ($skuModel) {
                $skuCode = $skuModel->sku;
            }
        }

        if (!$skuModel) {
            return [
                'success' => false,
                'message' => "Barcode \"{$barcode}\" tidak ditemukan. Pastikan kode seri atau SKU sudah terdaftar di sistem.",
            ];
        }

        // ── Resolve product name if not set ────────────────────────
        if ((!$produkName || $produkName === '-') && $skuCode) {
            $productList = \App\Models\ProductList::where('sku_name', $skuCode)->first();
            if ($productList) {
                $produkName = $productList->product;
            } else {
                $produkSku = \App\Models\ProdukSku::with('produk')->where('sku', $skuCode)->first();
                if ($produkSku && $produkSku->produk) {
                    $produkName = $produkSku->produk->nama_produk;
                }
            }
        }

        // Ensure SKU is active
        if (!$skuModel->is_active) {
            $skuModel->is_active = true;
            $skuModel->save();
        }

        // Build slot code for display
        $slotCode = $slotId;
        $slotParts = explode('__', $slotId);
        if (count($slotParts) >= 5) {
            $f = substr($slotParts[1] ?? '', 1);
            $b = strtoupper(substr($slotParts[2] ?? '', 1));
            $r = str_pad(substr($slotParts[3] ?? '', 1), 2, '0', STR_PAD_LEFT);
            $row = str_replace('ROW', '', $slotParts[4] ?? '');
            $slotCode = "L{$f}{$b}{$r}/{$row}";
        }

        // Construct cleaned barcode
        $cleanedBarcode = $barcode;
        if ($skuModel && $kodeSeri) {
            if ($nomorSeri !== null) {
                $cleanedBarcode = "{$skuModel->sku} | {$kodeSeri}.{$nomorSeri}";
            } else {
                if (str_contains($barcode, '|')) {
                    $cleanedBarcode = "{$skuModel->sku} | {$kodeSeri}";
                } else {
                    $cleanedBarcode = $skuModel->sku;
                }
            }
        }

        return [
            'success'        => true,
            'layout'         => $layout,
            'skuModel'       => $skuModel,
            'produkName'     => $produkName,
            'kodeSeri'       => $kodeSeri,
            'nomorSeri'      => $nomorSeri,
            'slotCode'       => $slotCode,
            'cleanedBarcode' => $cleanedBarcode,
        ];
    }

    /**
     * Scan barcode kode seri produk untuk masuk ke gudang.
     */
    public function scanProdukMasuk(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'barcode'   => 'required|string|max:500',
            'layout_id' => 'required|string|max:255',
            'slot_id'   => 'required|string|max:255',
        ]);

        $barcode  = trim($validated['barcode']);
        $layoutId = $validated['layout_id'];
        $slotId   = $validated['slot_id'];

        $resolved = $this->resolveBarcodeDetails($barcode, $layoutId, $slotId);

        if (!$resolved['success']) {
            return response()->json([
                'message' => $resolved['message'],
            ], 422);
        }

        $layout     = $resolved['layout'];
        $skuModel   = $resolved['skuModel'];
        $produkName = $resolved['produkName'];
        $kodeSeri   = $resolved['kodeSeri'];
        $nomorSeri  = $resolved['nomorSeri'];
        $slotCode   = $resolved['slotCode'];

        $entry    = null;
        $activity = null;
        $qty      = 1;

        DB::transaction(function () use ($layout, $slotId, $skuModel, $qty, $kodeSeri, $nomorSeri, &$entry, &$activity) {
            $entry = GudangProdukWorkspaceStockEntry::firstOrNew([
                'layout_id' => $layout->id,
                'slot_id'   => $slotId,
                'sku_id'    => $skuModel->id,
            ]);

            $entry->qty = (int) ($entry->qty ?? 0) + $qty;
            $entry->updated_by = auth()->id();
            $entry->save();

            $notes = 'Scan produk masuk';
            if ($kodeSeri) {
                $notes .= " | Kode seri: {$kodeSeri}";
            }
            if ($nomorSeri) {
                $notes .= ".{$nomorSeri}";
            }

            $activity = GudangProdukActivityLog::create([
                'type'         => 'placement',
                'sku_id'       => $skuModel->id,
                'from_slot_id' => null,
                'to_slot_id'   => $slotId,
                'qty'          => $qty,
                'notes'        => $notes,
                'created_by'   => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => "Produk berhasil di-scan dan masuk ke gudang.",
            'data' => [
                'barcode'    => $resolved['cleanedBarcode'] ?? $barcode,
                'kode_seri'  => $kodeSeri ?? '-',
                'nomor_seri' => $nomorSeri ?? '-',
                'sku'        => $skuModel->sku,
                'produk'     => $produkName ?? '-',
                'slot'       => $slotCode,
                'qty'        => $qty,
                'placement'  => [
                    'stockEntry' => [
                        'id'        => $entry?->id,
                        'layoutId'  => $layout->uid,
                        'slotId'    => $slotId,
                        'skuId'     => $skuModel->id,
                        'qty'       => (int) ($entry?->qty ?? 0),
                        'updatedAt' => optional($entry?->updated_at)->toISOString(),
                    ],
                    'activity' => [
                        'id'         => $activity?->id,
                        'type'       => 'placement',
                        'skuId'      => $skuModel->id,
                        'fromSlotId' => null,
                        'toSlotId'   => $slotId,
                        'qty'        => $qty,
                        'notes'      => $activity?->notes,
                        'createdAt'  => optional($activity?->created_at)->toISOString(),
                    ],
                ],
            ],
        ]);
    }

    public function checkScanProdukMasuk(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'barcode'   => 'required|string|max:500',
            'layout_id' => 'required|string|max:255',
            'slot_id'   => 'required|string|max:255',
        ]);

        $barcode  = trim($validated['barcode']);
        $layoutId = $validated['layout_id'];
        $slotId   = $validated['slot_id'];

        $resolved = $this->resolveBarcodeDetails($barcode, $layoutId, $slotId);

        if (!$resolved['success']) {
            return response()->json([
                'message' => $resolved['message'],
            ], 422);
        }

        return response()->json([
            'message' => "OK",
            'data' => [
                'barcode'    => $resolved['cleanedBarcode'] ?? $barcode,
                'kode_seri'  => $resolved['kodeSeri'] ?? '-',
                'nomor_seri' => $resolved['nomorSeri'] ?? '-',
                'sku'        => $resolved['skuModel']->sku,
                'produk'     => $resolved['produkName'] ?? '-',
                'slot'       => $resolved['slotCode'],
                'qty'        => 1,
            ],
        ]);
    }

    public function submitScanProdukMasuk(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'layout_id'    => 'required|string|max:255',
            'slot_id'      => 'required|string|max:255',
            'barcodes'     => 'required|array|min:1|max:1000',
            'barcodes.*'   => 'required|string|max:500',
        ]);

        $layoutId    = $validated['layout_id'];
        $slotId      = $validated['slot_id'];
        $barcodes    = array_map('trim', $validated['barcodes']);

        // Check duplicates within the request
        $barcodeCounts = array_count_values($barcodes);
        foreach ($barcodeCounts as $val => $count) {
            if ($count > 1) {
                return response()->json([
                    'message' => "Barcode \"{$val}\" terdeteksi duplikat dalam sesi ini.",
                ], 422);
            }
        }

        // Validate and resolve all barcodes first to avoid partial database writes on validation failure
        $resolvedBarcodes = [];
        foreach ($barcodes as $barcode) {
            $resolved = $this->resolveBarcodeDetails($barcode, $layoutId, $slotId);
            if (!$resolved['success']) {
                return response()->json([
                    'message' => "Gagal memproses \"{$barcode}\": " . $resolved['message'],
                ], 422);
            }
            $resolvedBarcodes[] = $resolved + ['barcode' => $barcode];
        }

        $results = [];
        $placements = [];

        DB::transaction(function () use ($resolvedBarcodes, $layoutId, $slotId, &$results, &$placements) {
            $qty = 1;
            foreach ($resolvedBarcodes as $resolved) {
                $layout    = $resolved['layout'];
                $skuModel  = $resolved['skuModel'];
                $kodeSeri  = $resolved['kodeSeri'];
                $nomorSeri = $resolved['nomorSeri'];
                $barcode   = $resolved['barcode'];
                $produkName = $resolved['produkName'];
                $slotCode  = $resolved['slotCode'];

                $entry = GudangProdukWorkspaceStockEntry::firstOrNew([
                    'layout_id' => $layout->id,
                    'slot_id'   => $slotId,
                    'sku_id'    => $skuModel->id,
                ]);

                $entry->qty = (int) ($entry->qty ?? 0) + $qty;
                $entry->updated_by = auth()->id();
                $entry->save();

                $notes = "Scan produk masuk";
                if ($kodeSeri) {
                    $notes .= " | Kode seri: {$kodeSeri}";
                }
                if ($nomorSeri) {
                    $notes .= ".{$nomorSeri}";
                }

                $activity = GudangProdukActivityLog::create([
                    'type'         => 'placement',
                    'sku_id'       => $skuModel->id,
                    'from_slot_id' => null,
                    'to_slot_id'   => $slotId,
                    'qty'          => $qty,
                    'notes'        => $notes,
                    'created_by'   => auth()->id(),
                ]);

                $placements[] = [
                    'stockEntry' => [
                        'id'        => $entry->id,
                        'layoutId'  => $layout->uid,
                        'slotId'    => $slotId,
                        'skuId'     => $skuModel->id,
                        'qty'       => (int) $entry->qty,
                        'updatedAt' => optional($entry->updated_at)->toISOString(),
                    ],
                    'activity' => [
                        'id'         => $activity->id,
                        'type'       => 'placement',
                        'skuId'      => $skuModel->id,
                        'fromSlotId' => null,
                        'toSlotId'   => $slotId,
                        'qty'        => $qty,
                        'notes'      => $activity->notes,
                        'createdAt'  => optional($activity->created_at)->toISOString(),
                    ],
                ];

                $results[] = [
                    'barcode'    => $barcode,
                    'kode_seri'  => $kodeSeri ?? '-',
                    'nomor_seri' => $nomorSeri ?? '-',
                    'sku'        => $skuModel->sku,
                    'produk'     => $produkName ?? '-',
                    'slot'       => $slotCode,
                    'qty'        => $qty,
                    'status'     => 'success',
                ];
            }
        });

        return response()->json([
            'message' => 'Seluruh produk berhasil di-scan masuk ke gudang.',
            'items' => $results,
            'placements' => $placements,
        ]);
    }

    public function getSeriScanDetails(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'seri_id' => 'nullable|integer',
            'nomor_seri' => 'nullable|string|max:255',
            'sequence' => 'nullable|integer',
        ]);

        $seri = null;
        $seriId = $validated['seri_id'] ?? $validated['id'] ?? null;

        if (!empty($seriId)) {
            $seri = \App\Models\Seri::find($seriId);
        }

        if (!$seri && !empty($validated['nomor_seri'])) {
            $nomorSeri = strtoupper(trim($validated['nomor_seri']));
            $seriModels = \App\Models\Seri::where('nomor_seri', $nomorSeri)->orderBy('id')->get();
            if ($seriModels->isNotEmpty()) {
                if (isset($validated['sequence'])) {
                    $seq = (int) $validated['sequence'];
                    $runningSum = 0;
                    foreach ($seriModels as $model) {
                        $start = $runningSum + 1;
                        $end = $runningSum + (int) $model->jumlah;
                        if ($seq >= $start && $seq <= $end) {
                            $seri = $model;
                            break;
                        }
                        $runningSum = $end;
                    }
                }
                if (!$seri) {
                    $seri = $seriModels->first();
                }
            }
        }

        if (!$seri) {
            $searchVal = $seriId ?? $validated['nomor_seri'] ?? '-';
            return response()->json([
                'message' => "Nomor seri \"{$searchVal}\" tidak ditemukan di sistem.",
            ], 422);
        }

        $skuCode = trim($seri->sku);
        $skuId = null;
        if (!empty($skuCode)) {
            $sku = \App\Models\Sku::where('sku', $skuCode)->first();
            if (!$sku) {
                // Try case-insensitive and character-insensitive match to resolve formatting discrepancies (spaces vs hyphens)
                $cleanSkuCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', $skuCode));
                $allSkus = \App\Models\Sku::all();
                foreach ($allSkus as $s) {
                    $cleanCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', $s->sku));
                    if ($cleanCode === $cleanSkuCode) {
                        $sku = $s;
                        break;
                    }
                }
                if (!$sku) {
                    // Try loose substring match (e.g. matching "DRESS LALISA - HITAM M" with "RTN-DRESS-LALISA-HITAM-M")
                    foreach ($allSkus as $s) {
                        $cleanCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', $s->sku));
                        if ($cleanCode !== '' && $cleanSkuCode !== '') {
                            $lenCode = strlen($cleanCode);
                            $lenSku = strlen($cleanSkuCode);
                            $minLen = min($lenCode, $lenSku);
                            $maxLen = max($lenCode, $lenSku);
                            
                            // Prevent short false matches (like matching single characters "L" or "A")
                            if ($minLen >= 4 && ($minLen / $maxLen) >= 0.5) {
                                if (str_contains($cleanCode, $cleanSkuCode) || str_contains($cleanSkuCode, $cleanCode)) {
                                    $sku = $s;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            if (!$sku) {
                // Auto-create Sku dynamically to prevent blocking warehouse operators
                $sku = \App\Models\Sku::create([
                    'sku' => $skuCode,
                    'is_active' => true,
                ]);
            }
            $skuId = $sku->id;
        }

        $jumlah = max(1, (int)$seri->jumlah);
        // Find starting index of printing (since multiple Seri objects can have same nomor_seri, 
        // the sequence counts sum of previous Seri objects with same nomor_seri and lower id)
        $nomorAwalCek = (int) \App\Models\Seri::where('nomor_seri', $seri->nomor_seri)
            ->where('id', '<', $seri->id)
            ->sum('jumlah');

        $prints = [];
        $this->ensureCancelledSeriPrintsTableReady();
        $cancelledPrints = DB::table(self::CANCELLED_SERI_PRINTS_TABLE)
            ->where('seri_id', $seri->id)
            ->pluck('created_at', 'barcode_seri')
            ->all();

        // Ambil semua placement activity logs untuk nomor seri ini dalam 1 query
        $activities = GudangProdukActivityLog::where('type', 'placement')
            ->where('notes', 'like', '%Kode seri: ' . $seri->nomor_seri . '.%')
            ->orderBy('created_at', 'desc')
            ->get();

        $scannedActivitiesMap = [];
        foreach ($activities as $activity) {
            if (preg_match('/Kode seri:\s*([^\s,|]+)/i', $activity->notes, $matches)) {
                $barcodeKey = trim($matches[1]);
                $barcodeKey = rtrim($barcodeKey, '., ');
                if (!isset($scannedActivitiesMap[$barcodeKey])) {
                    $scannedActivitiesMap[$barcodeKey] = $activity;
                }
            }
        }

        for ($i = 1; $i <= $jumlah; $i++) {
            $printSeq = $nomorAwalCek + $i;
            $barcode = "{$seri->nomor_seri}.{$printSeq}";

            // Lookup dari memory map alih-alih query database berulang kali
            $activity = $scannedActivitiesMap[$barcode] ?? null;

            $isScanned = !is_null($activity);
            $isCancelled = array_key_exists($barcode, $cancelledPrints);
            $scannedAt = $isScanned ? $activity->created_at : null;
            $slotCode = null;

            if ($isScanned && $activity->to_slot_id) {
                // Resolve slot code
                $slotId = $activity->to_slot_id;
                $slotCode = $slotId;
                $slotParts = explode('__', $slotId);
                if (count($slotParts) >= 5) {
                    $f = substr($slotParts[1] ?? '', 1);
                    $b = strtoupper(substr($slotParts[2] ?? '', 1));
                    $r = str_pad(substr($slotParts[3] ?? '', 1), 2, '0', STR_PAD_LEFT);
                    $row = str_replace('ROW', '', $slotParts[4] ?? '');
                    $slotCode = "L{$f}{$b}{$r}/{$row}";
                }
            }

            $prints[] = [
                'print_index' => $i,
                'print_seq' => $printSeq,
                'barcode_seri' => $barcode,
                'is_scanned' => $isScanned,
                'is_cancelled' => $isCancelled,
                'scanned_at' => $scannedAt,
                'cancelled_at' => $isCancelled ? $cancelledPrints[$barcode] : null,
                'slot_code' => $slotCode,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $seri->id,
                'nomor_seri' => $seri->nomor_seri,
                'sku' => $seri->sku,
                'sku_id' => $skuId,
                'jumlah' => $seri->jumlah,
                'scanned_count' => collect($prints)->where('is_scanned', true)->count(),
                'created_at' => $seri->created_at ? $seri->created_at->toIso8601String() : null,
                'prints' => $prints,
            ]
        ]);
    }

    public function cancelSeriPrint(Request $request)
    {
        $this->ensureWorkspaceTablesReady();
        $this->ensureCancelledSeriPrintsTableReady();

        $validated = $request->validate([
            'seri_id' => 'required|integer',
            'barcode_seri' => 'required|string|max:255',
            'reason' => 'nullable|string|max:255',
        ]);

        $seri = \App\Models\Seri::find($validated['seri_id']);
        if (!$seri) {
            return response()->json([
                'message' => 'Nomor seri tidak ditemukan.',
            ], 404);
        }

        $barcodeSeri = strtoupper(trim($validated['barcode_seri']));
        $prefix = strtoupper(trim($seri->nomor_seri)) . '.';
        if (!preg_match('/^' . preg_quote($prefix, '/') . '([0-9]+)$/', $barcodeSeri, $matches)) {
            return response()->json([
                'message' => "Barcode \"{$barcodeSeri}\" tidak sesuai dengan nomor seri aktif \"{$seri->nomor_seri}\".",
            ], 422);
        }

        $printSeq = (int) $matches[1];
        $nomorAwalCek = (int) \App\Models\Seri::where('nomor_seri', $seri->nomor_seri)
            ->where('id', '<', $seri->id)
            ->sum('jumlah');
        $firstSeq = $nomorAwalCek + 1;
        $lastSeq = $nomorAwalCek + max(1, (int) $seri->jumlah);

        if ($printSeq < $firstSeq || $printSeq > $lastSeq) {
            return response()->json([
                'message' => "Barcode \"{$barcodeSeri}\" berada di luar range cetak nomor seri ini.",
            ], 422);
        }

        if ($this->findPlacementActivityBySerial($barcodeSeri)) {
            return response()->json([
                'message' => "Kode seri \"{$barcodeSeri}\" sudah masuk gudang. Hapus scan terlebih dahulu jika ingin membatalkannya.",
            ], 422);
        }

        $now = now();
        DB::table(self::CANCELLED_SERI_PRINTS_TABLE)->updateOrInsert(
            ['barcode_seri' => $barcodeSeri],
            [
                'seri_id' => $seri->id,
                'nomor_seri' => $seri->nomor_seri,
                'print_seq' => $printSeq,
                'reason' => $validated['reason'] ?? 'Kelebihan cetak',
                'cancelled_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return response()->json([
            'message' => "Kode seri \"{$barcodeSeri}\" berhasil dibatalkan.",
            'data' => [
                'barcode_seri' => $barcodeSeri,
                'print_seq' => $printSeq,
                'is_cancelled' => true,
                'cancelled_at' => $now->toISOString(),
            ],
        ]);
    }

    public function deleteScanProdukMasuk(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'activity_id' => 'required|integer',
        ]);

        $activityId = $validated['activity_id'];

        $activity = GudangProdukActivityLog::where('id', $activityId)
            ->where('type', 'placement')
            ->first();

        if (!$activity) {
            return response()->json([
                'message' => 'Log pemindaian tidak ditemukan atau sudah dihapus.',
            ], 404);
        }

        DB::transaction(function () use ($activity) {
            // Find and decrement matching stock entry
            $entry = GudangProdukWorkspaceStockEntry::where('slot_id', $activity->to_slot_id)
                ->where('sku_id', $activity->sku_id)
                ->first();

            if ($entry) {
                $entry->qty = max(0, $entry->qty - $activity->qty);
                if ($entry->qty <= 0) {
                    $entry->delete();
                } else {
                    $entry->updated_by = auth()->id();
                    $entry->save();
                }
            }

            // Delete activity log
            $activity->delete();
        });

        return response()->json([
            'message' => 'Hasil scan berhasil dihapus dan stok gudang disesuaikan.',
        ]);
    }

    public function getStokAwalHistory(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);

        // 1. Get all placement activities starting with "Stok awal:"
        $query = GudangProdukActivityLog::query()
            ->join('skus', 'skus.id', '=', 'gudang_produk_activity_logs.sku_id')
            ->where('gudang_produk_activity_logs.type', 'placement')
            ->where('gudang_produk_activity_logs.notes', 'like', 'stok awal%');

        if ($startDate) {
            $query->whereDate('gudang_produk_activity_logs.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('gudang_produk_activity_logs.created_at', '<=', $endDate);
        }

        $activities = $query->select([
            'gudang_produk_activity_logs.id',
            'gudang_produk_activity_logs.sku_id',
            'gudang_produk_activity_logs.to_slot_id',
            'gudang_produk_activity_logs.qty',
            'gudang_produk_activity_logs.notes',
            'gudang_produk_activity_logs.created_at',
            'skus.sku as sku_code',
        ])
        ->get();

        // 2. Fetch layouts and aliases map to resolve slot labels
        $layoutsMap = DB::table('gudang_produk_layouts')
            ->pluck('name', 'uid')
            ->all();

        $aliasesMap = DB::table('gudang_produk_slot_aliases')
            ->pluck('alias', 'slot_id')
            ->all();

        // 3. Map logs to rows
        $rows = $activities->map(function ($activity) use ($layoutsMap, $aliasesMap) {
            $slotId = $activity->to_slot_id;
            $lokasi = $this->resolveSlotLabel($slotId, $layoutsMap, $aliasesMap) ?: $slotId;

            // Extract Kode seri from notes (e.g. "Stok awal: L4K01/1 | ... | Kode seri: SET MIKASA - MINT XL.2")
            $seri = '-';
            if (preg_match('/Kode seri:\s*([^|]+)/i', $activity->notes, $matches)) {
                $seri = trim($matches[1]);
            }

            return [
                'id' => (string) $activity->id,
                'tgl' => \Carbon\Carbon::parse($activity->created_at)->toISOString(),
                'sku' => $activity->sku_code,
                'seri' => $seri,
                'qty' => (int) $activity->qty,
                'lokasi' => $lokasi,
                'slot_id' => $slotId,
                'sku_id' => $activity->sku_id,
            ];
        });

        // 4. Group by SKU and lokasi
        $grouped = [];
        foreach ($rows as $row) {
            $groupKey = "{$row['sku']}__{$row['lokasi']}";
            
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'id' => $groupKey,
                    'tgl' => $row['tgl'],
                    'sku' => $row['sku'],
                    'lokasi' => $row['lokasi'],
                    'seriList' => $row['seri'] !== '-' ? [$row['seri']] : [],
                    'qty' => $row['qty'],
                    'sku_id' => $row['sku_id'],
                    'slot_id' => $row['slot_id'],
                ];
            } else {
                // Update quantity and serialize series list
                $grouped[$groupKey]['qty'] += $row['qty'];
                if ($row['seri'] !== '-') {
                    $grouped[$groupKey]['seriList'][] = $row['seri'];
                }
                
                // Use the latest date
                if ($row['tgl'] > $grouped[$groupKey]['tgl']) {
                    $grouped[$groupKey]['tgl'] = $row['tgl'];
                }
            }
        }

        // Finalize grouped rows with unique comma-separated series list
        $groupedRows = [];
        foreach ($grouped as $key => $g) {
            $seriList = array_unique($g['seriList']);
            $g['seri'] = !empty($seriList) ? implode(', ', $seriList) : '-';
            unset($g['seriList']);
            $groupedRows[] = $g;
        }

        // 5. Apply search filter
        if ($search !== '') {
            $searchLower = strtolower($search);
            $groupedRows = array_values(array_filter($groupedRows, function ($row) use ($searchLower) {
                return str_contains(strtolower($row['sku']), $searchLower) ||
                       str_contains(strtolower($row['seri']), $searchLower) ||
                       str_contains(strtolower($row['lokasi']), $searchLower);
            }));
        }

        // Sort by tgl desc
        usort($groupedRows, function ($a, $b) {
            return strcmp($b['tgl'], $a['tgl']);
        });

        // Calculate summary
        $totalQty = 0;
        $skus = [];
        $seris = [];
        $locations = [];
        foreach ($groupedRows as $r) {
            $totalQty += $r['qty'];
            if ($r['sku']) $skus[] = $r['sku'];
            if ($r['seri'] && $r['seri'] !== '-') {
                // series list could be comma-separated, split them
                $parts = array_map('trim', explode(',', $r['seri']));
                foreach ($parts as $p) {
                    if ($p !== '') $seris[] = $p;
                }
            }
            if ($r['lokasi']) $locations[] = $r['lokasi'];
        }

        $totalRows = count($groupedRows);
        $totalSku = count(array_unique($skus));
        $totalSeri = count(array_unique($seris));
        $totalLocations = count(array_unique($locations));

        // Paginate
        $lastPage = max((int) ceil($totalRows / $perPage), 1);
        $currentPage = min($page, $lastPage);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedData = array_slice($groupedRows, $offset, $perPage);

        return response()->json([
            'data' => $paginatedData,
            'summary' => [
                'total_rows' => $totalRows,
                'total_qty' => $totalQty,
                'total_sku' => $totalSku,
                'total_seri' => $totalSeri,
                'total_locations' => $totalLocations,
            ],
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $totalRows,
                'last_page' => $lastPage,
            ],
        ]);
    }

    private function resolveSlotLabel(?string $slotId, array $layoutsMap, array $aliasesMap): ?string
    {
        if (empty($slotId)) {
            return null;
        }

        // Parse standard format layout_xxx__Fxxx__Bxxx__Rxxx__ROWxxx
        if (preg_match('/^(.+?)__F(\d+)__B(.+?)__R(\d+)__ROW(\d+)$/', $slotId, $matches)) {
            $layoutUid = $matches[1];
            $floor = (int) $matches[2];
            $block = strtoupper($matches[3]);
            $rack = (int) $matches[4];
            $row = (int) $matches[5];

            $layoutName = $layoutsMap[$layoutUid] ?? $layoutUid;
            $slotCode = sprintf('L%s%s%s/%s', $floor, $block, str_pad((string)$rack, 2, '0', STR_PAD_LEFT), $row);
            
            $alias = $aliasesMap[$slotId] ?? null;
            $slotName = $alias ?: $slotCode;

            return "{$layoutName} - {$slotName}";
        }

        // Fallback checks for alias / layout maps even if layout ID format is custom
        $alias = $aliasesMap[$slotId] ?? null;
        if ($alias) {
            return $alias;
        }

        return $slotId;
    }

    public function updateStokAwalLocation(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'sku_id' => 'required|integer|exists:skus,id',
            'old_slot_id' => 'required|string|max:255',
            'new_layout_id' => 'required|string|max:255',
            'new_slot_id' => 'required|string|max:255',
        ]);

        $skuId = $validated['sku_id'];
        $oldSlotId = $validated['old_slot_id'];
        $newLayoutId = $validated['new_layout_id'];
        $newSlotId = $validated['new_slot_id'];

        $newLayout = GudangProdukLayout::with(['floors.blocks.racks'])
            ->where('uid', $newLayoutId)
            ->first();

        if (!$newLayout) {
            return response()->json([
                'message' => 'Layout gudang tujuan tidak ditemukan.',
            ], 422);
        }

        $validSlotIds = $this->buildSlotIdsFromLayoutModel($newLayout);
        if (!in_array($newSlotId, $validSlotIds, true)) {
            return response()->json([
                'message' => 'Slot tujuan tidak ditemukan pada layout yang dipilih.',
            ], 422);
        }

        // Find matching placement activity logs starting with 'stok awal'
        $activities = GudangProdukActivityLog::where('type', 'placement')
            ->where('sku_id', $skuId)
            ->where('to_slot_id', $oldSlotId)
            ->where('notes', 'like', 'stok awal%')
            ->get();

        if ($activities->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada history stok awal yang cocok untuk diedit.',
            ], 422);
        }

        $layoutsMap = DB::table('gudang_produk_layouts')
            ->pluck('name', 'uid')
            ->all();

        $aliasesMap = DB::table('gudang_produk_slot_aliases')
            ->pluck('alias', 'slot_id')
            ->all();

        $newSlotLabel = $this->resolveSlotLabel($newSlotId, $layoutsMap, $aliasesMap) ?: $newSlotId;

        DB::transaction(function () use ($activities, $skuId, $oldSlotId, $newLayout, $newSlotId, $newSlotLabel) {
            $totalQty = 0;
            foreach ($activities as $act) {
                $totalQty += $act->qty;

                // Update slot ID
                $act->to_slot_id = $newSlotId;
                
                // Update label in notes: "Stok awal: Gudang 1 - L4K01/1 | Kode seri: ..."
                if (preg_match('/^stok awal:\s*([^|]+)(.*)$/i', $act->notes, $matches)) {
                    $act->notes = "Stok awal: {$newSlotLabel}" . $matches[2];
                } else {
                    $act->notes = "Stok awal: {$newSlotLabel} | " . $act->notes;
                }
                
                $act->save();
            }

            // Adjust stock entry at old slot
            $oldEntry = GudangProdukWorkspaceStockEntry::where('slot_id', $oldSlotId)
                ->where('sku_id', $skuId)
                ->first();

            if ($oldEntry) {
                $oldEntry->qty = max(0, $oldEntry->qty - $totalQty);
                if ($oldEntry->qty <= 0) {
                    $oldEntry->delete();
                } else {
                    $oldEntry->updated_by = auth()->id();
                    $oldEntry->save();
                }
            }

            // Adjust stock entry at new slot
            $newEntry = GudangProdukWorkspaceStockEntry::firstOrNew([
                'layout_id' => $newLayout->id,
                'slot_id' => $newSlotId,
                'sku_id' => $skuId,
            ]);

            $newEntry->qty = (int) ($newEntry->qty ?? 0) + $totalQty;
            $newEntry->updated_by = auth()->id();
            $newEntry->save();
        });

        return response()->json([
            'message' => 'Lokasi stok awal berhasil diperbarui.',
        ]);
    }

    public function deleteStokAwal(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'sku_id' => 'required|integer|exists:skus,id',
            'slot_id' => 'required|string|max:255',
        ]);

        $skuId = $validated['sku_id'];
        $slotId = $validated['slot_id'];

        // Find all placement activity logs for this sku and slot starting with 'stok awal'
        $activities = GudangProdukActivityLog::where('type', 'placement')
            ->where('sku_id', $skuId)
            ->where('to_slot_id', $slotId)
            ->where('notes', 'like', 'stok awal%')
            ->get();

        if ($activities->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada history stok awal yang cocok untuk dihapus.',
            ], 422);
        }

        DB::transaction(function () use ($activities, $skuId, $slotId) {
            $totalQty = 0;
            foreach ($activities as $act) {
                $totalQty += $act->qty;
                $act->delete();
            }

            // Adjust stock entry at the slot
            $entry = GudangProdukWorkspaceStockEntry::where('slot_id', $slotId)
                ->where('sku_id', $skuId)
                ->first();

            if ($entry) {
                $entry->qty = max(0, $entry->qty - $totalQty);
                if ($entry->qty <= 0) {
                    $entry->delete();
                } else {
                    $entry->updated_by = auth()->id();
                    $entry->save();
                }
            }
        });

        return response()->json([
            'message' => 'History stok awal berhasil dihapus dan stok disesuaikan.',
        ]);
    }

    private function autoFixMismatchedPendingSessions()
    {
        $sessions = GudangProdukPlacementSession::where('status', 'pending')->get();
        foreach ($sessions as $session) {
            $barcodes = $session->barcodes ?? [];
            if (empty($barcodes)) continue;
            
            $firstBarcode = null;
            foreach ($barcodes as $b) {
                $firstBarcode = $b['barcode'] ?? $b['serialCode'] ?? null;
                if ($firstBarcode) break;
            }
            if (!$firstBarcode) continue;
            
            $serial = $firstBarcode;
            if (str_contains($firstBarcode, '|')) {
                $parts = array_map('trim', explode('|', $firstBarcode, 2));
                $serial = $parts[1] ?? $firstBarcode;
            }
            
            $lastDot = strrpos($serial, '.');
            $kodeSeri = ($lastDot !== false) ? substr($serial, 0, $lastDot) : $serial;
            
            $currentSeri = \App\Models\Seri::find($session->seri_id);
            if (!$currentSeri || $currentSeri->nomor_seri !== $kodeSeri) {
                $correctSeri = \App\Models\Seri::where('nomor_seri', $kodeSeri)->first();
                if ($correctSeri) {
                    $skuCode = trim($correctSeri->sku);
                    $correctSku = \App\Models\Sku::firstOrCreate(
                        ['sku' => $skuCode],
                        ['is_active' => true]
                    );
                    
                    $session->update([
                        'seri_id' => $correctSeri->id,
                        'sku_id' => $correctSku->id,
                    ]);
                }
            }
        }
    }
}

