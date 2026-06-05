<?php

namespace App\Http\Controllers;

use App\Exports\ProductListExport;
use App\Models\ProductList;
use App\Models\ProductListImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductListController extends Controller
{
    private const EXPORT_COLUMNS = [
        'id' => 'id',
        'sku_name' => 'sku_name',
        'product' => 'product',
        'product_group' => 'product_group',
        'product_size' => 'product_size',
        'product_source' => 'product_source',
        'product_colour' => 'product_colour',
        'product_material_group_1' => 'product_material_group_1',
        'product_colour_1' => 'product_colour_1',
        'product_material_group_2' => 'product_material_group_2',
        'product_colour_2' => 'product_colour_2',
        'product_accecories' => 'product_accecories',
        'product_accecories_colour' => 'product_accecories_colour',
        'estimasi_cutting' => 'estimasi_cutting',
        'estimasi_combi' => 'estimasi_combi',
        'berat_panjang' => 'berat_panjang',
        'satuan_berat_panjang' => 'satuan_berat_panjang',
        'berat_panjang_combi' => 'berat_panjang_combi',
        'satuan_berat_panjang_combi' => 'satuan_berat_panjang_combi',
        'ld' => 'LD',
        'pj_dress' => 'pj_dress',
        'pj_celana' => 'pj_celana',
        'pj_baju' => 'pj_baju',
        'notes_spk' => 'notes_spk',
        'price_cmt' => 'price_cmt',
        'price_cutting' => 'price_cutting',
        'material_count' => 'material_count',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function index(Request $request)
    {
        if ($request->boolean('opname_products')) {
            return $this->opnameProducts($request);
        }

        $perPage = min(max((int) $request->get('per_page', 10), 1), 100);

        $query = ProductList::with('productListImage');
        $this->applyFilters($query, $request);

        $paginator = $query->orderByDesc('id')->paginate($perPage);
        $items = $paginator->getCollection()->map(function (ProductList $item) {
            return $this->transformItem($item);
        })->values();

        $summaryQuery = ProductList::query();
        $this->applyFilters($summaryQuery, $request);
        $summaryRows = (clone $summaryQuery)->get(['product_group', 'product_source', 'materials', 'material_count']);

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'summary' => [
                'total' => $summaryRows->count(),
                'products' => $summaryRows->pluck('product')->filter()->unique()->values(),
                'groups' => $summaryRows->pluck('product_group')->filter()->unique()->values(),
                'sources' => $summaryRows->pluck('product_source')->filter()->unique()->values(),
                'material_groups_1' => $summaryRows->map(function ($row) {
                    $m = $this->normalizeMaterials($row->materials);
                    return $m[0]['material_group'] ?? null;
                })->filter()->unique()->values(),
                'material_groups_2' => $summaryRows->map(function ($row) {
                    $m = $this->normalizeMaterials($row->materials);
                    return $m[1]['material_group'] ?? null;
                })->filter()->unique()->values(),
                'material_rows' => $summaryRows->sum(function ($row) {
                    return $row->material_count ?: count($this->normalizeMaterials($row->materials));
                }),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $payload = $this->buildProductListPayload($validated);

        $productList = ProductList::create($payload)->load('productListImage');

        return response()->json([
            'message' => 'Product List berhasil ditambahkan.',
            'data' => $this->transformItem($productList),
        ], 201);
    }

    public function show($id)
    {
        $productList = ProductList::with('productListImage')->findOrFail($id);

        return response()->json([
            'data' => $this->transformItem($productList),
        ]);
    }

    public function update(Request $request, $id)
    {
        $productList = ProductList::findOrFail($id);
        $validated = $this->validatePayload($request, $productList->id);
        $payload = $this->buildProductListPayload($validated);

        $productList->update($payload);

        // Sinkronisasi global: Update field umum untuk semua size dari product yang sama
        if (!empty($productList->product)) {
            ProductList::where('product', $productList->product)
                ->where('id', '!=', $productList->id)
                ->update([
                    'berat_panjang' => $productList->berat_panjang,
                    'satuan_berat_panjang' => $productList->satuan_berat_panjang,
                    'berat_panjang_combi' => $productList->berat_panjang_combi,
                    'satuan_berat_panjang_combi' => $productList->satuan_berat_panjang_combi,
                    'pj_dress' => $productList->pj_dress,
                    'pj_celana' => $productList->pj_celana,
                    'pj_baju' => $productList->pj_baju,
                    'price_cmt' => $productList->price_cmt,
                    'price_cutting' => $productList->price_cutting,
                ]);

            // Sinkronisasi khusus size: Update LD dan Product Source hanya untuk product & ukuran yang sama
            if (!empty($productList->product_size)) {
                ProductList::where('product', $productList->product)
                    ->where('product_size', $productList->product_size)
                    ->where('id', '!=', $productList->id)
                    ->update([
                        'ld' => $productList->ld,
                        'product_source' => $productList->product_source,
                    ]);
            }
        }

        return response()->json([
            'message' => 'Product List berhasil diperbarui.',
            'data' => $this->transformItem($productList->fresh('productListImage')),
        ]);
    }

    public function destroy($id)
    {
        $productList = ProductList::findOrFail($id);
        $productList->delete();

        return response()->json([
            'message' => 'Product List berhasil dihapus.',
        ]);
    }

    public function downloadTemplate()
    {
        $headers = [
            'product', 'sku_name', 'product_group', 'product_size', 'product_source', 'product_colour',
            'product_material_1', 'product_colour_1', 'product_material_group_1',
            'product_material_2', 'product_colour_2', 'product_material_group_2',
            'product_accecories', 'product_accecories_colour', 'estimasi_cutting', 'estimasi_combi',
            'berat_panjang', 'satuan_berat_panjang', 'berat_panjang_combi', 'satuan_berat_panjang_combi',
            'ld', 'id_s', 'id_m', 'id_l', 'id_xl', 'pj_dress', 'pj_celana', 'pj_baju', 'price_cmt', 'price_cutting', 'notes_spk'
        ];

        return Excel::download(new ProductListExport($headers, []), 'template_product_list.xlsx');
    }

    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $path = $validated['image']->store('product_list_images', 'public');

        $image = ProductListImage::create([
            'image_path' => $path,
        ]);

        return response()->json([
            'message' => 'Foto berhasil diupload.',
            'data' => $this->transformImage($image),
        ], 201);
    }

    public function assignImage(Request $request)
    {
        $validated = $request->validate([
            'product_list_image_id' => 'required|exists:product_list_images,id',
            'product_list_ids' => 'required|array|min:1',
            'product_list_ids.*' => 'integer|exists:product_lists,id',
        ]);

        ProductList::whereIn('id', $validated['product_list_ids'])->update([
            'product_list_image_id' => $validated['product_list_image_id'],
        ]);

        return response()->json([
            'message' => 'Foto berhasil di-assign ke Product List.',
        ]);
    }

    public function export(Request $request)
    {
        $columns = collect($request->input('columns', []))
            ->filter(function ($column) {
                return array_key_exists($column, self::EXPORT_COLUMNS);
            })
            ->values()
            ->all();

        if (empty($columns)) {
            return response()->json([
                'message' => 'Pilih minimal satu kolom untuk export.',
            ], 422);
        }

        $sortBy = $request->input('sortBy', 'id');
        $sortOrder = strtolower($request->input('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'sku_name', 'product', 'product_group', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        $query = ProductList::with('productListImage');
        $this->applyFilters($query, $request);

        $rows = $query->orderBy($sortBy, $sortOrder)->get()->map(function (ProductList $item) use ($columns) {
            $flattened = $this->flattenItemForExport($item);
            return collect($columns)->map(function ($column) use ($flattened) {
                return $flattened[$column] ?? '';
            })->all();
        })->all();

        $fileName = 'product-list-' . now()->format('Y-m-d-His') . '.xlsx';

        $headings = collect($columns)->map(function ($column) {
            return self::EXPORT_COLUMNS[$column];
        })->all();

        return Excel::download(new ProductListExport($headings, $rows), $fileName);
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $spreadsheet = IOFactory::load($validated['file']->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return response()->json([
                'message' => 'File import kosong atau tidak memiliki data.',
            ], 422);
        }

        $headers = array_map(function ($value) {
            return Str::lower(trim((string) $value));
        }, $rows[0]);

        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $emptySkuRows = 0;
        $duplicateSkuRows = 0;
        $errors = [];
        $seenSkus = [];
        $importRows = [];
        $importSkus = [];
        $now = now();

        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;
            $mapped = $this->mapImportedRow($headers, $row);

            if (!$this->rowHasContent($mapped)) {
                continue;
            }

            $processed++;
            $skuName = trim((string) ($mapped['sku_name'] ?? ''));

            if ($skuName === '') {
                $skipped++;
                $emptySkuRows++;
                continue;
            }

            $skuKey = Str::lower($skuName);
            if (isset($seenSkus[$skuKey])) {
                $skipped++;
                $duplicateSkuRows++;
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => 'SKU Name duplikat di file import.',
                ];
                continue;
            }

            $seenSkus[$skuKey] = true;
            $payload = $this->buildProductListPayload($mapped + ['sku_name' => $skuName]);
            $payload['materials'] = json_encode($payload['materials']);
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            if (!$payload['product']) {
                $payload['product'] = '-';
            }

            $importRows[] = $payload;
            $importSkus[] = $skuName;
        }

        if (!empty($importRows)) {
            try {
                $existingSkus = ProductList::query()
                    ->whereIn('sku_name', $importSkus)
                    ->pluck('sku_name')
                    ->all();
                $existingSkuSet = array_fill_keys($existingSkus, true);

                foreach ($importSkus as $skuName) {
                    if (isset($existingSkuSet[$skuName])) {
                        $updated++;
                    } else {
                        $created++;
                    }
                }

                $updateColumns = array_values(array_diff(array_keys($importRows[0]), ['sku_name', 'created_at']));

                DB::transaction(function () use ($importRows, $updateColumns) {
                    foreach (array_chunk($importRows, 500) as $chunk) {
                        ProductList::upsert($chunk, ['sku_name'], $updateColumns);
                    }
                });
            } catch (\Throwable $e) {
                $skipped += count($importRows);
                $created = 0;
                $updated = 0;
                $errors[] = [
                    'row' => '-',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'empty_sku_rows' => $emptySkuRows,
            'duplicate_sku_rows' => $duplicateSkuRows,
            'total_errors' => count($errors),
            'errors' => $errors,
        ]);
    }

    private function validatePayload(Request $request, $ignoreId = null)
    {
        $validated = $request->validate([
            'product' => 'required|string|max:255',
            'sku_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_lists', 'sku_name')->ignore($ignoreId),
            ],
            'product_group' => 'nullable|string|max:255',
            'product_size' => 'nullable|string|max:255',
            'product_source' => 'nullable|string|max:255',
            'product_colour' => 'nullable|string|max:255',
            'product_accecories' => 'nullable|string|max:255',
            'product_accecories_colour' => 'nullable|string|max:255',
            'estimasi_cutting' => 'nullable|integer|min:0',
            'estimasi_combi' => 'nullable|integer|min:0',
            'berat_panjang' => 'nullable|numeric|min:0',
            'satuan_berat_panjang' => 'nullable|string|max:255',
            'berat_panjang_combi' => 'nullable|numeric|min:0',
            'satuan_berat_panjang_combi' => 'nullable|string|max:255',
            'LD' => 'nullable|numeric|min:0',
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
            'materials' => 'nullable|array',
            'materials.*.material' => 'nullable|string|max:255',
            'materials.*.colour' => 'nullable|string|max:255',
            'materials.*.material_group' => 'nullable|string|max:255',
            'materials.*.kind' => 'nullable|string|max:50',
        ]);

        $validated['materials'] = $this->normalizeMaterials($validated['materials'] ?? []);

        return $validated;
    }

    private function buildProductListPayload(array $data, ProductList $existing = null)
    {
        $productGroup = $this->stringOrNull($data['product_group'] ?? ($existing ? $existing->product_group : null));
        $productSize = $this->stringOrNull($data['product_size'] ?? ($existing ? $existing->product_size : null));
        
        $productSource = trim(($productGroup ?? '') . ' ' . ($productSize ?? ''));
        if ($productSource === '') {
            $productSource = null;
        }

        $materials = $this->normalizeMaterials($data['materials'] ?? ($existing ? $existing->materials : []));
        $payload = [
            'product' => $this->stringOrNull($data['product'] ?? ($existing ? $existing->product : null)),
            'sku_name' => $this->stringOrNull($data['sku_name'] ?? ($existing ? $existing->sku_name : null)),
            'product_group' => $productGroup,
            'product_size' => $productSize,
            'product_source' => $productSource,
            'product_colour' => $this->stringOrNull($data['product_colour'] ?? ($existing ? $existing->product_colour : null)),
            'materials' => $materials,
            'material_count' => count($materials),
            'product_accecories' => $this->stringOrNull($data['product_accecories'] ?? ($existing ? $existing->product_accecories : null)),
            'product_accecories_colour' => $this->stringOrNull($data['product_accecories_colour'] ?? ($existing ? $existing->product_accecories_colour : null)),
            'estimasi_cutting' => $this->integerOrNull($data['estimasi_cutting'] ?? ($existing ? $existing->estimasi_cutting : null)),
            'estimasi_combi' => $this->integerOrNull($data['estimasi_combi'] ?? ($existing ? $existing->estimasi_combi : null)),
            'berat_panjang' => $this->decimalOrNull($data['berat_panjang'] ?? ($existing ? $existing->berat_panjang : null)),
            'satuan_berat_panjang' => $this->stringOrNull($data['satuan_berat_panjang'] ?? ($existing ? $existing->satuan_berat_panjang : null)),
            'berat_panjang_combi' => $this->decimalOrNull($data['berat_panjang_combi'] ?? ($existing ? $existing->berat_panjang_combi : null)),
            'satuan_berat_panjang_combi' => $this->stringOrNull($data['satuan_berat_panjang_combi'] ?? ($existing ? $existing->satuan_berat_panjang_combi : null)),
            'ld' => $this->decimalOrNull($data['LD'] ?? ($data['ld'] ?? ($existing ? $existing->ld : null))),
            'id_s' => $this->stringOrNull($data['id_s'] ?? ($existing ? $existing->id_s : null)),
            'id_m' => $this->stringOrNull($data['id_m'] ?? ($existing ? $existing->id_m : null)),
            'id_l' => $this->stringOrNull($data['id_l'] ?? ($existing ? $existing->id_l : null)),
            'id_xl' => $this->stringOrNull($data['id_xl'] ?? ($existing ? $existing->id_xl : null)),
            'pj_dress' => $this->decimalOrNull($data['pj_dress'] ?? ($existing ? $existing->pj_dress : null)),
            'pj_celana' => $this->stringOrNull($data['pj_celana'] ?? ($existing ? $existing->pj_celana : null)),
            'pj_baju' => $this->decimalOrNull($data['pj_baju'] ?? ($existing ? $existing->pj_baju : null)),
            'price_cmt' => $this->decimalOrNull($data['price_cmt'] ?? ($existing ? $existing->price_cmt : null)),
            'price_cutting' => $this->decimalOrNull($data['price_cutting'] ?? ($existing ? $existing->price_cutting : null)),
            'notes_spk' => $this->stringOrNull($data['notes_spk'] ?? ($existing ? $existing->notes_spk : null)),
        ];

        if ($existing && $existing->product_list_image_id) {
            $payload['product_list_image_id'] = $existing->product_list_image_id;
        }

        return $payload;
    }

    private function opnameProducts(Request $request)
    {
        $showAll = $request->boolean('all');

        $query = ProductList::query()
            ->select('product')
            ->selectRaw('COUNT(DISTINCT product_lists.id) as sku_count')
            ->whereNotNull('product')
            ->where('product', '<>', '');

        if (!$showAll) {
            $query->join('skus as opname_skus', 'opname_skus.sku', '=', 'product_lists.sku_name')
                ->join('gudang_produk_stock_entries as opname_stock', function ($join) {
                    $join->on('opname_stock.sku_id', '=', 'opname_skus.id')
                        ->where('opname_stock.qty', '>', 0);
                })
                ->where('opname_skus.is_active', true);
        }

        $this->applyFilters($query, $request);

        $products = $query
            ->groupBy('product')
            ->orderBy('product')
            ->limit(500)
            ->get();

        return response()->json([
            'data' => $products->map(function ($row) {
                $product = trim((string) $row->product);

                return [
                    'id' => 'product-list:' . Str::slug($product),
                    'name' => $product,
                    'source' => 'product-list',
                    'meta' => ((int) $row->sku_count) . ' SKU Product List',
                ];
            })->values()->all(),
        ]);
    }

    private function applyFilters($query, Request $request)
    {
        $exactProduct = trim((string) $request->get('exact_product', ''));
        if ($exactProduct !== '') {
            $query->where('product', $exactProduct);
        }

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('product', 'like', "%{$search}%")
                    ->orWhere('sku_name', 'like', "%{$search}%")
                    ->orWhere('product_group', 'like', "%{$search}%")
                    ->orWhere('product_size', 'like', "%{$search}%")
                    ->orWhere('product_source', 'like', "%{$search}%")
                    ->orWhere('product_colour', 'like', "%{$search}%")
                    ->orWhere('product_accecories', 'like', "%{$search}%")
                    ->orWhere('product_accecories_colour', 'like', "%{$search}%")
                    ->orWhere('id_s', 'like', "%{$search}%")
                    ->orWhere('id_m', 'like', "%{$search}%")
                    ->orWhere('id_l', 'like', "%{$search}%")
                    ->orWhere('id_xl', 'like', "%{$search}%")
                    ->orWhere('notes_spk', 'like', "%{$search}%")
                    ->orWhere('materials', 'like', "%{$search}%");
            });
        }

        $productGroup = trim((string) $request->get('product_group', ''));
        if ($productGroup !== '') {
            $query->where('product_group', $productGroup);
        }

        $materialGroup1 = trim((string) $request->get('material_group_1', ''));
        if ($materialGroup1 !== '') {
            $query->where('materials', 'like', '%"material_group":"' . $materialGroup1 . '"%');
        }

        $materialGroup2 = trim((string) $request->get('material_group_2', ''));
        if ($materialGroup2 !== '') {
            $query->where('materials', 'like', '%"material_group":"' . $materialGroup2 . '"%');
        }

        return $query;
    }

    private function transformItem(ProductList $item)
    {
        $item->loadMissing('productListImage');

        return [
            'id' => $item->id,
            'product' => $item->product,
            'sku_name' => $item->sku_name,
            'product_group' => $item->product_group,
            'product_size' => $item->product_size,
            'product_source' => $item->product_source,
            'product_colour' => $item->product_colour,
            'materials' => $this->normalizeMaterials($item->materials),
            'material_count' => $item->material_count ?: count($this->normalizeMaterials($item->materials)),
            'product_accecories' => $item->product_accecories,
            'product_accecories_colour' => $item->product_accecories_colour,
            'estimasi_cutting' => $item->estimasi_cutting,
            'estimasi_combi' => $item->estimasi_combi,
            'berat_panjang' => $this->toNumericOrNull($item->berat_panjang),
            'satuan_berat_panjang' => $item->satuan_berat_panjang,
            'berat_panjang_combi' => $this->toNumericOrNull($item->berat_panjang_combi),
            'satuan_berat_panjang_combi' => $item->satuan_berat_panjang_combi,
            'LD' => $this->toNumericOrNull($item->ld),
            'id_s' => $item->id_s,
            'id_m' => $item->id_m,
            'id_l' => $item->id_l,
            'id_xl' => $item->id_xl,
            'pj_dress' => $this->toNumericOrNull($item->pj_dress),
            'pj_celana' => $item->pj_celana,
            'pj_baju' => $this->toNumericOrNull($item->pj_baju),
            'price_cmt' => $this->toNumericOrNull($item->price_cmt),
            'price_cutting' => $this->toNumericOrNull($item->price_cutting),
            'notes_spk' => $item->notes_spk,
            'product_list_image' => $item->productListImage ? $this->transformImage($item->productListImage) : null,
            'created_at' => optional($item->created_at)->toDateTimeString(),
            'updated_at' => optional($item->updated_at)->toDateTimeString(),
        ];
    }

    private function transformImage(ProductListImage $image)
    {
        return [
            'id' => $image->id,
            'image_path' => $image->image_path,
            'image_url' => $image->image_url,
        ];
    }

    private function flattenItemForExport(ProductList $item)
    {
        $materials = collect($this->normalizeMaterials($item->materials));
        $flattened = [
            'id' => $item->id,
            'sku_name' => $item->sku_name,
            'product' => $item->product,
            'product_group' => $item->product_group,
            'product_size' => $item->product_size,
            'product_source' => $item->product_source,
            'product_colour' => $item->product_colour,
            'product_accecories' => $item->product_accecories,
            'product_accecories_colour' => $item->product_accecories_colour,
            'estimasi_cutting' => $item->estimasi_cutting,
            'estimasi_combi' => $item->estimasi_combi,
            'berat_panjang' => $this->toNumericOrNull($item->berat_panjang),
            'satuan_berat_panjang' => $item->satuan_berat_panjang,
            'berat_panjang_combi' => $this->toNumericOrNull($item->berat_panjang_combi),
            'satuan_berat_panjang_combi' => $item->satuan_berat_panjang_combi,
            'LD' => $this->toNumericOrNull($item->ld),
            'id_s' => $item->id_s,
            'id_m' => $item->id_m,
            'id_l' => $item->id_l,
            'id_xl' => $item->id_xl,
            'pj_dress' => $this->toNumericOrNull($item->pj_dress),
            'pj_celana' => $item->pj_celana,
            'pj_baju' => $this->toNumericOrNull($item->pj_baju),
            'notes_spk' => $item->notes_spk,
            'price_cmt' => $this->toNumericOrNull($item->price_cmt),
            'price_cutting' => $this->toNumericOrNull($item->price_cutting),
            'material_count' => $item->material_count ?: $materials->count(),
            'created_at' => optional($item->created_at)->toDateTimeString(),
            'updated_at' => optional($item->updated_at)->toDateTimeString(),
        ];

        for ($i = 1; $i <= 2; $i++) {
            $material = $materials->get($i - 1, []);
            $flattened["product_material_{$i}"] = $material['material'] ?? '';
            $flattened["product_colour_{$i}"] = $material['colour'] ?? '';
            $flattened["product_material_group_{$i}"] = $material['material_group'] ?? '';
        }

        return $flattened;
    }

    private function mapImportedRow(array $headers, array $row)
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $mapped[$header] = $row[$index] ?? null;
        }

        $materials = [];
        for ($i = 1; $i <= 2; $i++) {
            $material = $this->stringOrNull($mapped["product_material_{$i}"] ?? null);
            $colour = $this->stringOrNull($mapped["product_colour_{$i}"] ?? null);
            $materialGroup = $this->stringOrNull($mapped["product_material_group_{$i}"] ?? null);

            if ($material || $colour || $materialGroup) {
                $materials[] = [
                    'kind' => $i === 1 ? 'utama' : 'kombinasi',
                    'material' => $material,
                    'colour' => $colour,
                    'material_group' => $materialGroup,
                ];
            }
        }

        return [
            'product' => $this->stringOrNull($mapped['product'] ?? null),
            'sku_name' => $this->stringOrNull($mapped['sku_name'] ?? null),
            'product_group' => $this->stringOrNull($mapped['product_group'] ?? null),
            'product_size' => $this->stringOrNull($mapped['product_size'] ?? null),
            'product_source' => $this->stringOrNull($mapped['product_source'] ?? null),
            'product_colour' => $this->stringOrNull($mapped['product_colour'] ?? null),
            'product_accecories' => $this->stringOrNull($mapped['product_accecories'] ?? null),
            'product_accecories_colour' => $this->stringOrNull($mapped['product_accecories_colour'] ?? null),
            'estimasi_cutting' => $this->integerOrNull($mapped['estimasi_cutting'] ?? null),
            'estimasi_combi' => $this->integerOrNull($mapped['estimasi_combi'] ?? null),
            'berat_panjang' => $this->decimalOrNull($mapped['berat_panjang'] ?? null),
            'satuan_berat_panjang' => $this->stringOrNull($mapped['satuan_berat_panjang'] ?? null),
            'berat_panjang_combi' => $this->decimalOrNull($mapped['berat_panjang_combi'] ?? null),
            'satuan_berat_panjang_combi' => $this->stringOrNull($mapped['satuan_berat_panjang_combi'] ?? null),
            'LD' => $this->decimalOrNull($mapped['ld'] ?? null),
            'id_s' => $this->stringOrNull($mapped['id_s'] ?? null),
            'id_m' => $this->stringOrNull($mapped['id_m'] ?? null),
            'id_l' => $this->stringOrNull($mapped['id_l'] ?? null),
            'id_xl' => $this->stringOrNull($mapped['id_xl'] ?? null),
            'pj_dress' => $this->decimalOrNull($mapped['pj_dress'] ?? null),
            'pj_celana' => $this->stringOrNull($mapped['pj_celana'] ?? null),
            'pj_baju' => $this->decimalOrNull($mapped['pj_baju'] ?? null),
            'price_cmt' => $this->decimalOrNull($mapped['price_cmt'] ?? null),
            'price_cutting' => $this->decimalOrNull($mapped['price_cutting'] ?? null),
            'notes_spk' => $this->stringOrNull($mapped['notes_spk'] ?? null),
            'materials' => $materials,
        ];
    }

    private function normalizeMaterials($materials)
    {
        $source = is_array($materials) ? $materials : [];

        return array_values(array_filter(array_map(function ($item, $index) {
            return [
                'material' => $this->stringOrNull($item['material'] ?? null),
                'colour' => $this->stringOrNull($item['colour'] ?? null),
                'material_group' => $this->stringOrNull($item['material_group'] ?? null),
                'kind' => $this->stringOrNull($item['kind'] ?? null) ?: ($index === 0 ? 'utama' : 'kombinasi'),
            ];
        }, $source, array_keys($source)), function ($item) {
            return $item['material'] || $item['colour'] || $item['material_group'];
        }));
    }

    private function rowHasContent(array $row)
    {
        foreach ($row as $value) {
            if (is_array($value)) {
                if (!empty($value)) {
                    return true;
                }
                continue;
            }

            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function stringOrNull($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function integerOrNull($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round((float) $value);
    }

    private function decimalOrNull($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    public function spkOptions()
    {
        // Get all products grouped by product_group
        // This is specifically for SPK Cutting dropdown
        $products = ProductList::select('id', 'product', 'product_group', 'sku_name', 'product_colour', 'product_size', 'price_cutting', 'estimasi_cutting', 'estimasi_combi')
            ->orderBy('product_group')
            ->orderBy('product')
            ->get()
            ->groupBy('product_group');

        $result = [];
        foreach ($products as $productGroupName => $skus) {
            if (!$productGroupName || $productGroupName === '-') continue;
            
            $firstSku = $skus->first();
            $result[] = [
                'id' => $firstSku->id,
                'product' => $firstSku->product,
                'product_group' => $productGroupName,
                'price_cutting' => $firstSku->price_cutting,
                'estimasi_cutting' => $firstSku->estimasi_cutting,
                'estimasi_combi' => $firstSku->estimasi_combi,
                'skus' => $skus->map(function ($sku) {
                    return [
                        'id' => $sku->id,
                        'sku_id' => $sku->id,
                        'sku_name' => $sku->sku_name,
                        'product_colour' => $sku->product_colour,
                        'product_size' => $sku->product_size,
                    ];
                })->values()->all(),
            ];
        }

        return response()->json(['data' => $result]);
    }

    private function toNumericOrNull($value)
    {
        return $value === null || $value === '' ? null : (float) $value;
    }


}
