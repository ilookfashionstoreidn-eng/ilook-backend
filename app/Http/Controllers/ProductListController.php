<?php

namespace App\Http\Controllers;

use App\Exports\ProductListExport;
use App\Models\ProductList;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ProductListController extends Controller
{
    private const SUMMARY_CACHE_KEY = 'product_lists.summary.v3';
    private const IMPORT_READ_CHUNK_SIZE = 5000;
    private const IMPORT_DB_BATCH_SIZE = 1000;

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $search = trim((string) $request->query('search', ''));
        $productGroup = trim((string) $request->query('product_group', ''));
        $productSource = trim((string) $request->query('product_source', ''));
        $sortBy = $request->query('sortBy', 'id');
        $sortOrder = strtolower($request->query('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'product', 'sku_name', 'product_group', 'product_source', 'created_at', 'updated_at'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        $query = ProductList::query();

        if ($search !== '') {
            $searchPrefix = $this->escapeLike($search) . '%';

            $query->where(function ($q) use ($search, $searchPrefix) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('product', 'like', $searchPrefix)
                    ->orWhere('sku_name', 'like', $searchPrefix)
                    ->orWhere('product_group', 'like', $searchPrefix)
                    ->orWhere('product_size', 'like', $searchPrefix)
                    ->orWhere('product_source', 'like', $searchPrefix)
                    ->orWhere('product_colour', 'like', $searchPrefix)
                    ->orWhere('id_s', 'like', $searchPrefix)
                    ->orWhere('id_m', 'like', $searchPrefix)
                    ->orWhere('id_l', 'like', $searchPrefix)
                    ->orWhere('id_xl', 'like', $searchPrefix);
            });
        }

        if ($productGroup !== '') {
            $query->where('product_group', $productGroup);
        }

        if ($productSource !== '') {
            $query->where('product_source', $productSource);
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($sortBy !== 'id') {
            $query->orderBy('id', $sortOrder);
        }

        $productLists = $query->paginate($perPage);

        return response()->json([
            'data' => $productLists->items(),
            'current_page' => $productLists->currentPage(),
            'last_page' => $productLists->lastPage(),
            'per_page' => $productLists->perPage(),
            'total' => $productLists->total(),
            'from' => $productLists->firstItem(),
            'to' => $productLists->lastItem(),
            'summary' => $this->summary(),
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, true);
        $materials = $this->normalizeMaterials($request->input('materials', []));
        $validated['materials'] = $materials;
        $validated['material_count'] = count($materials);

        $productList = ProductList::create($validated);
        $this->forgetSummaryCache();

        return response()->json($productList, Response::HTTP_CREATED);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:204800',
        ]);

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $worksheetInfo = $this->getWorksheetInfo($filePath);
        $highestRow = max(0, (int) ($worksheetInfo['totalRows'] ?? 0));
        $lastColumn = $worksheetInfo['lastColumnLetter'] ?? 'ZZ';

        if ($highestRow < 2) {
            return response()->json([
                'message' => 'File Excel tidak memiliki data untuk diimport.',
            ], 422);
        }

        $rows = $this->readSpreadsheetRows($filePath, 1, min(15, $highestRow), $lastColumn);
        [$headerRowNumber, $headers] = $this->detectHeaderRow($rows);

        if ($headerRowNumber === null) {
            return response()->json([
                'message' => 'Header Excel tidak ditemukan. Pastikan ada kolom Product atau SKU Name.',
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $processed = 0;
        $skipped = 0;
        $emptySkuRows = 0;
        $duplicateSkuRows = 0;
        $errors = [];
        $batch = [];
        $seenSkuNames = [];

        try {
            for ($startRow = $headerRowNumber + 1; $startRow <= $highestRow; $startRow += self::IMPORT_READ_CHUNK_SIZE) {
                $endRow = min($startRow + self::IMPORT_READ_CHUNK_SIZE - 1, $highestRow);
                $rows = $this->readSpreadsheetRows($filePath, $startRow, $endRow, $lastColumn);

                foreach ($rows as $rowNumber => $row) {
                    if ($rowNumber <= $headerRowNumber) {
                        continue;
                    }

                    $payload = $this->mapImportRow($row, $headers);

                    if ($this->isImportRowEmpty($payload)) {
                        continue;
                    }

                    if (trim((string) ($payload['product'] ?? '')) === '') {
                        $skipped++;
                        $errors[] = [
                            'row' => $rowNumber,
                            'message' => 'Kolom Product wajib diisi.',
                        ];
                        continue;
                    }

                    $preparedPayload = $this->prepareImportPayload($payload);
                    $processed++;

                    $skuKey = $this->normalizeImportKeyPart($preparedPayload['sku_name'] ?? '');

                    if ($skuKey === '') {
                        $emptySkuRows++;
                    } elseif (isset($seenSkuNames[$skuKey])) {
                        $duplicateSkuRows++;
                    } else {
                        $seenSkuNames[$skuKey] = true;
                    }

                    $batch[] = $preparedPayload;

                    if (count($batch) >= self::IMPORT_DB_BATCH_SIZE) {
                        $result = $this->persistImportBatch($batch);
                        $created += $result['created'];
                        $updated += $result['updated'];
                        $batch = [];
                    }
                }

                unset($rows);
            }

            if (!empty($batch)) {
                $result = $this->persistImportBatch($batch);
                $created += $result['created'];
                $updated += $result['updated'];
            }

            if (($created + $updated) > 0) {
                $this->forgetSummaryCache();
            }
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Import gagal diproses.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Import Product List selesai.',
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'empty_sku_rows' => $emptySkuRows,
            'duplicate_sku_rows' => $duplicateSkuRows,
            'errors' => array_slice($errors, 0, 25),
            'total_errors' => count($errors),
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'columns' => 'nullable|array|min:1|max:40',
            'columns.*' => 'required|string',
            'search' => 'nullable|string|max:255',
            'product_group' => 'nullable|string|max:255',
            'product_source' => 'nullable|string|max:255',
            'sortBy' => 'nullable|string|max:50',
            'sortOrder' => 'nullable|string|in:asc,desc,ASC,DESC',
        ]);

        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $columns = ProductListExport::sanitizeColumns($validated['columns'] ?? []);
        $fileName = 'product-list-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new ProductListExport($columns, [
            'search' => $validated['search'] ?? '',
            'product_group' => $validated['product_group'] ?? '',
            'product_source' => $validated['product_source'] ?? '',
            'sortBy' => $validated['sortBy'] ?? 'id',
            'sortOrder' => $validated['sortOrder'] ?? 'desc',
        ]), $fileName);
    }

    public function hppCatalog(Request $request)
    {
        $productGroup = trim((string) $request->query('product_group', ''));

        if ($productGroup === '') {
            return response()->json([
                'groups' => $this->summary()['groups'] ?? [],
            ], Response::HTTP_OK);
        }

        $rows = ProductList::query()
            ->where('product_group', $productGroup)
            ->orderBy('id')
            ->get([
                'id',
                'product_group',
                'product',
                'sku_name',
                'product_colour',
                'product_size',
                'id_s',
                'id_m',
                'id_l',
                'id_xl',
                'pj_dress',
                'pj_celana',
                'pj_baju',
                'price_cmt',
                'price_cutting',
            ]);

        if ($rows->isEmpty()) {
            return response()->json([
                'message' => 'Product group tidak ditemukan pada Product List.',
            ], Response::HTTP_NOT_FOUND);
        }

        [$jenisProduk, $namaProduk] = $this->splitProductGroup($productGroup);

        $skuItems = $rows
            ->filter(function ($row) {
                return trim((string) $row->sku_name) !== '';
            })
            ->unique(function ($row) {
                return strtolower(trim((string) $row->sku_name));
            })
            ->values()
            ->map(function ($row) {
                return [
                    'sku' => trim((string) $row->sku_name),
                    'warna' => trim((string) ($row->product_colour ?? '')),
                    'ukuran' => trim((string) ($row->product_size ?? '')),
                ];
            })
            ->all();

        $first = $rows->first();

        return response()->json([
            'product_group' => $productGroup,
            'jenis_produk' => $jenisProduk,
            'nama_produk' => $namaProduk,
            'ld_s' => $this->firstFilledValue($rows, 'id_s'),
            'ld_m' => $this->firstFilledValue($rows, 'id_m'),
            'ld_l' => $this->firstFilledValue($rows, 'id_l'),
            'ld_xl' => $this->firstFilledValue($rows, 'id_xl'),
            'pj_dress' => $this->firstFilledValue($rows, 'pj_dress'),
            'pj_celana' => $this->firstFilledValue($rows, 'pj_celana'),
            'pj_baju' => $this->firstFilledValue($rows, 'pj_baju'),
            'price_cmt' => $this->firstFilledValue($rows, 'price_cmt'),
            'price_cutting' => $this->firstFilledValue($rows, 'price_cutting'),
            'sku_items' => $skuItems,
            'source_product' => trim((string) ($first->product ?? '')),
        ], Response::HTTP_OK);
    }

    private function getWorksheetInfo(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);

        if (method_exists($reader, 'listWorksheetInfo')) {
            $info = $reader->listWorksheetInfo($filePath);

            if (!empty($info[0])) {
                return $info[0];
            }
        }

        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $info = [
            'totalRows' => $sheet->getHighestDataRow(),
            'lastColumnLetter' => $sheet->getHighestDataColumn(),
        ];
        $spreadsheet->disconnectWorksheets();

        return $info;
    }

    private function readSpreadsheetRows(string $filePath, int $startRow, int $endRow, string $lastColumn): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new ProductListImportReadFilter($startRow, $endRow));

        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->rangeToArray(
            "A{$startRow}:{$lastColumn}{$endRow}",
            null,
            true,
            true,
            true
        );
        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    private function prepareImportPayload(array $payload): array
    {
        $payload['materials'] = $this->normalizeMaterials($payload['materials'] ?? []);
        $payload['material_count'] = count($payload['materials']);

        foreach (['estimasi_cutting', 'estimasi_combi', 'pj_dress', 'pj_baju', 'price_cmt', 'price_cutting'] as $numericField) {
            $payload[$numericField] = $this->normalizeImportNumber($payload[$numericField] ?? null);
        }

        $payload['pj_celana'] = $this->normalizeImportText($payload['pj_celana'] ?? null);

        return $payload;
    }

    private function persistImportBatch(array $payloads): array
    {
        if (empty($payloads)) {
            return ['created' => 0, 'updated' => 0];
        }

        $now = now();
        $skuNames = collect($payloads)
            ->pluck('sku_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingBySku = empty($skuNames)
            ? []
            : ProductList::query()
                ->whereIn('sku_name', $skuNames)
                ->pluck('id', 'sku_name')
                ->all();
        $existingWithoutSkuByKey = $this->getExistingWithoutSkuByNaturalKey($payloads);

        $insertBySku = [];
        $insertByNaturalKey = [];
        $insertWithoutSku = [];
        $updateById = [];

        foreach ($payloads as $payload) {
            $row = $this->toDatabaseRow($payload, $now);
            $skuName = trim((string) ($payload['sku_name'] ?? ''));

            if ($skuName !== '' && isset($existingBySku[$skuName])) {
                $row['id'] = $existingBySku[$skuName];
                $updateById[$row['id']] = $row;
                continue;
            }

            if ($skuName !== '') {
                $insertBySku[$skuName] = $row;
                continue;
            }

            $naturalKey = $this->makeImportNaturalKey($payload);

            if ($naturalKey !== '' && isset($existingWithoutSkuByKey[$naturalKey])) {
                $row['id'] = $existingWithoutSkuByKey[$naturalKey];
                $updateById[$row['id']] = $row;
                continue;
            }

            if ($naturalKey !== '') {
                $insertByNaturalKey[$naturalKey] = $row;
                continue;
            }

            $insertWithoutSku[] = $row;
        }

        DB::transaction(function () use ($updateById, $insertBySku, $insertByNaturalKey, $insertWithoutSku) {
            $updateRows = array_values($updateById);

            if (!empty($updateRows)) {
                DB::table('product_lists')->upsert(
                    $updateRows,
                    ['id'],
                    [
                        'product',
                        'sku_name',
                        'product_group',
                        'product_size',
                        'product_source',
                        'product_colour',
                        'materials',
                        'material_count',
                        'estimasi_cutting',
                        'estimasi_combi',
                        'id_s',
                        'id_m',
                        'id_l',
                        'id_xl',
                        'pj_dress',
                        'pj_celana',
                        'pj_baju',
                        'price_cmt',
                        'price_cutting',
                        'notes_spk',
                        'updated_at',
                    ]
                );
            }

            $insertRows = array_merge(array_values($insertBySku), array_values($insertByNaturalKey), $insertWithoutSku);

            foreach (array_chunk($insertRows, 500) as $chunk) {
                DB::table('product_lists')->insert($chunk);
            }
        });

        return [
            'created' => count($insertBySku) + count($insertByNaturalKey) + count($insertWithoutSku),
            'updated' => count($updateById),
        ];
    }

    private function getExistingWithoutSkuByNaturalKey(array $payloads): array
    {
        $products = [];

        foreach ($payloads as $payload) {
            if (trim((string) ($payload['sku_name'] ?? '')) !== '') {
                continue;
            }

            $product = trim((string) ($payload['product'] ?? ''));

            if ($product !== '') {
                $products[$product] = true;
            }
        }

        if (empty($products)) {
            return [];
        }

        $rows = ProductList::query()
            ->where(function ($query) {
                $query->whereNull('sku_name')->orWhere('sku_name', '');
            })
            ->whereIn('product', array_keys($products))
            ->get([
                'id',
                'product',
                'product_group',
                'product_size',
                'product_source',
                'product_colour',
                'id_s',
                'id_m',
                'id_l',
                'id_xl',
            ]);

        $existingByKey = [];

        foreach ($rows as $row) {
            $key = $this->makeImportNaturalKey([
                'product' => $row->product,
                'product_group' => $row->product_group,
                'product_size' => $row->product_size,
                'product_source' => $row->product_source,
                'product_colour' => $row->product_colour,
                'id_s' => $row->id_s,
                'id_m' => $row->id_m,
                'id_l' => $row->id_l,
                'id_xl' => $row->id_xl,
            ]);

            if ($key !== '' && !isset($existingByKey[$key])) {
                $existingByKey[$key] = $row->id;
            }
        }

        return $existingByKey;
    }

    private function makeImportNaturalKey(array $payload): string
    {
        $product = $this->normalizeImportKeyPart($payload['product'] ?? '');

        if ($product === '') {
            return '';
        }

        return implode('|', [
            $product,
            $this->normalizeImportKeyPart($payload['product_group'] ?? ''),
            $this->normalizeImportKeyPart($payload['product_size'] ?? ''),
            $this->normalizeImportKeyPart($payload['product_source'] ?? ''),
            $this->normalizeImportKeyPart($payload['product_colour'] ?? ''),
            $this->normalizeImportKeyPart($payload['id_s'] ?? ''),
            $this->normalizeImportKeyPart($payload['id_m'] ?? ''),
            $this->normalizeImportKeyPart($payload['id_l'] ?? ''),
            $this->normalizeImportKeyPart($payload['id_xl'] ?? ''),
        ]);
    }

    private function toDatabaseRow(array $payload, $timestamp): array
    {
        return [
            'product' => $payload['product'] ?? '',
            'sku_name' => ($payload['sku_name'] ?? '') !== '' ? $payload['sku_name'] : null,
            'product_group' => ($payload['product_group'] ?? '') !== '' ? $payload['product_group'] : null,
            'product_size' => ($payload['product_size'] ?? '') !== '' ? $payload['product_size'] : null,
            'product_source' => ($payload['product_source'] ?? '') !== '' ? $payload['product_source'] : null,
            'product_colour' => ($payload['product_colour'] ?? '') !== '' ? $payload['product_colour'] : null,
            'materials' => json_encode($payload['materials'] ?? [], JSON_UNESCAPED_UNICODE),
            'material_count' => (int) ($payload['material_count'] ?? 0),
            'estimasi_cutting' => $payload['estimasi_cutting'] ?? null,
            'estimasi_combi' => $payload['estimasi_combi'] ?? null,
            'id_s' => ($payload['id_s'] ?? '') !== '' ? $payload['id_s'] : null,
            'id_m' => ($payload['id_m'] ?? '') !== '' ? $payload['id_m'] : null,
            'id_l' => ($payload['id_l'] ?? '') !== '' ? $payload['id_l'] : null,
            'id_xl' => ($payload['id_xl'] ?? '') !== '' ? $payload['id_xl'] : null,
            'pj_dress' => $payload['pj_dress'] ?? null,
            'pj_celana' => ($payload['pj_celana'] ?? '') !== '' ? $payload['pj_celana'] : null,
            'pj_baju' => $payload['pj_baju'] ?? null,
            'price_cmt' => $payload['price_cmt'] ?? null,
            'price_cutting' => $payload['price_cutting'] ?? null,
            'notes_spk' => ($payload['notes_spk'] ?? '') !== '' ? $payload['notes_spk'] : null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    public function show($id)
    {
        return response()->json(ProductList::findOrFail($id), Response::HTTP_OK);
    }

    public function update(Request $request, $id)
    {
        $productList = ProductList::findOrFail($id);
        $validated = $this->validatePayload($request, false, $productList->id);
        $materials = $this->normalizeMaterials($request->input('materials', []));
        $validated['materials'] = $materials;
        $validated['material_count'] = count($materials);

        $productList->update($validated);
        $this->forgetSummaryCache();

        return response()->json($productList->fresh(), Response::HTTP_OK);
    }

    public function destroy($id)
    {
        $productList = ProductList::findOrFail($id);
        $productList->delete();
        $this->forgetSummaryCache();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function validatePayload(Request $request, bool $isStore = false, ?int $ignoreId = null): array
    {
        $uniqueSkuNameRule = Rule::unique('product_lists', 'sku_name');
        if ($ignoreId !== null) {
            $uniqueSkuNameRule->ignore($ignoreId);
        }

        $skuNameRules = [
            $isStore ? 'required' : 'nullable',
            'string',
            'max:255',
            $uniqueSkuNameRule,
        ];

        return $request->validate([
            'product' => 'required|string|max:255',
            'sku_name' => $skuNameRules,
            'product_group' => 'nullable|string|max:255',
            'product_size' => 'nullable|string|max:255',
            'product_source' => 'nullable|string|max:255',
            'product_colour' => 'nullable|string|max:255',
            'materials' => 'nullable|array',
            'materials.*.material' => 'nullable|string|max:255',
            'materials.*.colour' => 'nullable|string|max:255',
            'materials.*.material_group' => 'nullable|string|max:255',
            'estimasi_cutting' => 'nullable|numeric|min:0',
            'estimasi_combi' => 'nullable|numeric|min:0',
            'id_s' => 'nullable|string|max:255',
            'id_m' => 'nullable|string|max:255',
            'id_l' => 'nullable|string|max:255',
            'id_xl' => 'nullable|string|max:255',
            'pj_dress' => 'nullable|numeric|min:0',
            'pj_celana' => 'nullable|string|max:255',
            'pj_baju' => 'nullable|numeric|min:0',
            'price_cmt' => 'nullable|numeric|min:0',
            'price_cutting' => 'nullable|numeric|min:0',
            'notes_spk' => 'nullable|string',
        ]);
    }

    private function detectHeaderRow(array $rows): array
    {
        $maxScanRows = min(count($rows), 15);

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber > $maxScanRows) {
                break;
            }

            $normalizedHeaders = [];
            $hasProductHeader = false;

            foreach ($row as $column => $value) {
                $normalized = $this->normalizeHeader($value);
                $normalizedHeaders[$column] = $normalized;

                if (in_array($normalized, ['product', 'produk', 'nama_produk', 'sku_name', 'sku'], true)) {
                    $hasProductHeader = true;
                }
            }

            if ($hasProductHeader) {
                return [$rowNumber, $normalizedHeaders];
            }
        }

        return [null, []];
    }

    private function mapImportRow(array $row, array $headers): array
    {
        $payload = [
            'product' => '',
            'sku_name' => '',
            'product_group' => '',
            'product_size' => '',
            'product_source' => '',
            'product_colour' => '',
            'materials' => [],
            'estimasi_cutting' => null,
            'estimasi_combi' => null,
            'id_s' => '',
            'id_m' => '',
            'id_l' => '',
            'id_xl' => '',
            'pj_dress' => null,
            'pj_celana' => null,
            'pj_baju' => null,
            'price_cmt' => null,
            'price_cutting' => null,
            'notes_spk' => '',
        ];

        $materialRows = [];

        foreach ($headers as $column => $header) {
            $value = $this->normalizeImportText($row[$column] ?? null);
            $field = $this->resolveImportField($header);

            if ($field) {
                $payload[$field] = $value;
                continue;
            }

            $materialMatch = $this->resolveMaterialField($header);

            if ($materialMatch) {
                [$materialIndex, $materialField] = $materialMatch;

                if (!isset($materialRows[$materialIndex])) {
                    $materialRows[$materialIndex] = [
                        'material' => '',
                        'colour' => '',
                        'material_group' => '',
                    ];
                }

                $materialRows[$materialIndex][$materialField] = $value;
            }
        }

        ksort($materialRows);
        $payload['materials'] = array_values($materialRows);

        return $payload;
    }

    private function resolveImportField(string $header): ?string
    {
        $aliases = [
            'product' => ['product', 'produk', 'nama_produk', 'product_name', 'nama_product'],
            'sku_name' => ['sku_name', 'sku', 'nama_sku', 'sku_produk', 'product_sku', 'sku_product'],
            'product_group' => ['product_group', 'produk_group', 'group_produk', 'grup_produk', 'group', 'kategori_produk', 'product_category'],
            'product_size' => ['product_size', 'size', 'ukuran_produk', 'product_ukuran'],
            'product_source' => ['product_source', 'source', 'sumber', 'sumber_produk', 'asal_produk'],
            'product_colour' => ['product_colour', 'product_color', 'colour', 'color', 'warna_produk', 'warna'],
            'estimasi_cutting' => ['estimasi_cutting', 'estimate_cutting', 'est_cutting', 'cutting'],
            'estimasi_combi' => ['estimasi_combi', 'estimate_combi', 'est_combi', 'combi'],
            'id_s' => ['id_s', 'ld_s', 'ukuran_s'],
            'id_m' => ['id_m', 'ld_m', 'ukuran_m'],
            'id_l' => ['id_l', 'ld_l', 'ukuran_l'],
            'id_xl' => ['id_xl', 'ld_xl', 'ukuran_xl'],
            'pj_dress' => ['pj_dress', 'panjang_dress'],
            'pj_celana' => ['pj_celana', 'panjang_celana'],
            'pj_baju' => ['pj_baju', 'panjang_baju'],
            'price_cmt' => ['price_cmt', 'harga_cmt', 'cmt_price', 'harga_jahit'],
            'price_cutting' => ['price_cutting', 'harga_cutting', 'cutting_price', 'harga_potong'],
            'notes_spk' => ['notes_spk', 'note_spk', 'notes', 'note', 'catatan_spk', 'catatan'],
        ];

        foreach ($aliases as $field => $fieldAliases) {
            if (in_array($header, $fieldAliases, true)) {
                return $field;
            }
        }

        return null;
    }

    private function resolveMaterialField(string $header): ?array
    {
        $patterns = [
            '/^(?:product_)?material_(\d+)$/',
            '/^(?:product_)?bahan_(\d+)$/',
            '/^(?:product_)?colour_(\d+)$/',
            '/^(?:product_)?color_(\d+)$/',
            '/^(?:product_)?warna_(\d+)$/',
            '/^(?:product_)?material_group_(\d+)$/',
            '/^(?:product_)?bahan_group_(\d+)$/',
            '/^(?:product_)?group_material_(\d+)$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $header, $matches)) {
                $field = 'material';

                if (strpos($pattern, 'colour') !== false || strpos($pattern, 'color') !== false || strpos($pattern, 'warna') !== false) {
                    $field = 'colour';
                }

                if (strpos($pattern, 'group') !== false) {
                    $field = 'material_group';
                }

                return [(int) $matches[1], $field];
            }
        }

        if (in_array($header, ['product_material', 'material', 'bahan'], true)) {
            return [1, 'material'];
        }

        if (in_array($header, ['product_material_colour', 'product_material_color', 'material_colour', 'material_color', 'warna_bahan'], true)) {
            return [1, 'colour'];
        }

        if (in_array($header, ['product_material_group', 'material_group', 'group_bahan'], true)) {
            return [1, 'material_group'];
        }

        return null;
    }

    private function normalizeHeader($value): string
    {
        $header = strtolower(trim((string) $value));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        $header = preg_replace('/_+/', '_', $header);

        return trim($header, '_');
    }

    private function normalizeImportText($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function normalizeImportKeyPart($value): string
    {
        $normalized = trim((string) ($value ?? ''));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return strtolower($normalized);
    }

    private function normalizeImportNumber($value): ?float
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return null;
        }

        $clean = preg_replace('/[^\d,.\-]/', '', $raw);

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $clean)) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (substr_count($clean, ',') === 1 && substr_count($clean, '.') === 0) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function isImportRowEmpty(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if ($key === 'materials') {
                foreach ($value as $material) {
                    if (!$this->isMaterialRowEmpty($material)) {
                        return false;
                    }
                }
                continue;
            }

            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isMaterialRowEmpty(array $material): bool
    {
        return trim((string) ($material['material'] ?? '')) === ''
            && trim((string) ($material['colour'] ?? '')) === ''
            && trim((string) ($material['material_group'] ?? '')) === '';
    }

    private function normalizeMaterials($materials): array
    {
        if (!is_array($materials)) {
            return [];
        }

        $normalized = [];

        foreach ($materials as $material) {
            $row = [
                'material' => trim((string) ($material['material'] ?? '')),
                'colour' => trim((string) ($material['colour'] ?? '')),
                'material_group' => trim((string) ($material['material_group'] ?? '')),
            ];

            if ($row['material'] !== '' || $row['colour'] !== '' || $row['material_group'] !== '') {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    private function summary(): array
    {
        return Cache::remember(self::SUMMARY_CACHE_KEY, now()->addMinutes(10), function () {
            return [
                'total' => ProductList::query()->count(),
                'groups' => ProductList::query()
                    ->whereNotNull('product_group')
                    ->where('product_group', '<>', '')
                    ->distinct()
                    ->orderBy('product_group')
                    ->pluck('product_group')
                    ->values()
                    ->all(),
                'sources' => ProductList::query()
                    ->whereNotNull('product_source')
                    ->where('product_source', '<>', '')
                    ->distinct()
                    ->orderBy('product_source')
                    ->pluck('product_source')
                    ->values()
                    ->all(),
                'material_rows' => (int) ProductList::query()->sum('material_count'),
            ];
        });
    }

    private function forgetSummaryCache(): void
    {
        Cache::forget(self::SUMMARY_CACHE_KEY);
    }

    private function splitProductGroup(string $group): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $group));

        if ($normalized === '') {
            return ['', ''];
        }

        $parts = explode(' ', $normalized);
        $jenis = strtoupper((string) ($parts[0] ?? ''));
        $nama = trim(implode(' ', array_slice($parts, 1)));

        if ($nama === '') {
            $nama = $normalized;
        }

        return [$jenis, $nama];
    }

    private function firstFilledValue($rows, string $key)
    {
        foreach ($rows as $row) {
            $value = $row->{$key} ?? null;

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

class ProductListImportReadFilter implements IReadFilter
{
    private int $startRow;
    private int $endRow;

    public function __construct(int $startRow, int $endRow)
    {
        $this->startRow = $startRow;
        $this->endRow = $endRow;
    }

    public function readCell($columnAddress, $row, $worksheetName = '')
    {
        return $row >= $this->startRow && $row <= $this->endRow;
    }
}
