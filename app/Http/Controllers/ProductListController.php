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
        'id' => 'ID',
        'sku_name' => 'SKU Name',
        'product' => 'Product',
        'product_group' => 'Product Group',
        'product_size' => 'Product Size',
        'product_source' => 'Product Source',
        'product_colour' => 'Product Colour',
        'product_material_1' => 'product_material_1',
        'product_colour_1' => 'product_colour_1',
        'product_material_group_1' => 'product_material_group_1',
        'product_material_2' => 'product_material_2',
        'product_colour_2' => 'product_colour_2',
        'product_material_group_2' => 'product_material_group_2',
        'product_material_3' => 'product_material_3',
        'product_colour_3' => 'product_colour_3',
        'product_material_group_3' => 'product_material_group_3',
        'product_material_4' => 'product_material_4',
        'product_colour_4' => 'product_colour_4',
        'product_material_group_4' => 'product_material_group_4',
        'product_material_5' => 'product_material_5',
        'product_colour_5' => 'product_colour_5',
        'product_material_group_5' => 'product_material_group_5',
        'product_material_6' => 'product_material_6',
        'product_colour_6' => 'product_colour_6',
        'product_material_group_6' => 'product_material_group_6',
        'estimasi_cutting' => 'Estimasi Cutting',
        'estimasi_combi' => 'Estimasi Combi',
        'id_s' => 'ID S',
        'id_m' => 'ID M',
        'id_l' => 'ID L',
        'id_xl' => 'ID XL',
        'pj_dress' => 'PJ Dress',
        'pj_celana' => 'PJ Celana',
        'pj_baju' => 'PJ Baju',
        'notes_spk' => 'notes_spk',
        'price_cmt' => 'Price CMT',
        'price_cutting' => 'Price Cutting',
        'material_count' => 'Total Material',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
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
                'groups' => $summaryRows->pluck('product_group')->filter()->unique()->values(),
                'sources' => $summaryRows->pluck('product_source')->filter()->unique()->values(),
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

        return Excel::download(new ProductListExport($columns, $rows), $fileName);
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

            try {
                DB::transaction(function () use ($mapped, $skuName, &$created, &$updated) {
                    $existing = ProductList::where('sku_name', $skuName)->first();
                    $payload = $this->buildProductListPayload($mapped + ['sku_name' => $skuName], $existing);

                    if ($existing) {
                        $existing->update($payload);
                        $updated++;
                        return;
                    }

                    ProductList::create($payload);
                    $created++;
                });
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = [
                    'row' => $rowNumber,
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
            'estimasi_cutting' => 'nullable|integer|min:0',
            'estimasi_combi' => 'nullable|integer|min:0',
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
        $materials = $this->normalizeMaterials($data['materials'] ?? ($existing ? $existing->materials : []));
        $payload = [
            'product' => $this->stringOrNull($data['product'] ?? ($existing ? $existing->product : null)),
            'sku_name' => $this->stringOrNull($data['sku_name'] ?? ($existing ? $existing->sku_name : null)),
            'product_group' => $this->stringOrNull($data['product_group'] ?? ($existing ? $existing->product_group : null)),
            'product_size' => $this->stringOrNull($data['product_size'] ?? ($existing ? $existing->product_size : null)),
            'product_source' => $this->stringOrNull($data['product_source'] ?? ($existing ? $existing->product_source : null)),
            'product_colour' => $this->stringOrNull($data['product_colour'] ?? ($existing ? $existing->product_colour : null)),
            'materials' => $materials,
            'material_count' => count($materials),
            'estimasi_cutting' => $this->integerOrNull($data['estimasi_cutting'] ?? ($existing ? $existing->estimasi_cutting : null)),
            'estimasi_combi' => $this->integerOrNull($data['estimasi_combi'] ?? ($existing ? $existing->estimasi_combi : null)),
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
        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('product', 'like', "%{$search}%")
                    ->orWhere('sku_name', 'like', "%{$search}%")
                    ->orWhere('product_group', 'like', "%{$search}%")
                    ->orWhere('product_size', 'like', "%{$search}%")
                    ->orWhere('product_source', 'like', "%{$search}%")
                    ->orWhere('product_colour', 'like', "%{$search}%")
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
            'estimasi_cutting' => $item->estimasi_cutting,
            'estimasi_combi' => $item->estimasi_combi,
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
            'estimasi_cutting' => $item->estimasi_cutting,
            'estimasi_combi' => $item->estimasi_combi,
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

        for ($i = 1; $i <= 6; $i++) {
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
        for ($i = 1; $i <= 6; $i++) {
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
            'estimasi_cutting' => $this->integerOrNull($mapped['estimasi_cutting'] ?? null),
            'estimasi_combi' => $this->integerOrNull($mapped['estimasi_combi'] ?? null),
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

    private function toNumericOrNull($value)
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
