<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\Bahan;
use App\Models\ProdukSku;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\Aksesoris;
use Illuminate\Support\Facades\Storage;
use App\Models\ProdukUpdateHistory;
use App\Models\ProductList;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;


class ProdukController extends Controller
{
    public function index(Request $request)
    {
        $kategoriProduk = $request->query('kategori_produk');
        $statusProduk = $request->query('status_produk');
        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');
        $perPage = $request->query('per_page', 7);
        $page = $request->query('page', 1);
        $search = $request->query('search', '');

        // include relasi bahan/aksesoris agar detail komponen lengkap
        $query = Produk::with(['komponen.bahan', 'komponen.aksesoris']);

        // Filter kategori
        if ($kategoriProduk) {
            $query->where('kategori_produk', $kategoriProduk);
        }

        // Filter status
        if ($statusProduk) {
            $query->where('status_produk', $statusProduk);
        }

        // Search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_produk', 'like', '%' . $search . '%')
                    ->orWhere('product_group', 'like', '%' . $search . '%')
                    ->orWhere('jenis_produk', 'like', '%' . $search . '%');
            });
        }

        // Pagination
        $produk = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        // Transform data
        $produk->getCollection()->transform(function ($item) {
            $item->gambar_produk = asset('storage/' . $item->gambar_produk);
            $item->total_komponen = $item->komponen->sum('total_harga_bahan');
            return $item;
        });

        return response()->json([
            'data' => $produk->items(),
            'current_page' => $produk->currentPage(),
            'last_page' => $produk->lastPage(),
            'per_page' => $produk->perPage(),
            'total' => $produk->total(),
            'from' => $produk->firstItem(),
            'to' => $produk->lastItem(),
        ], Response::HTTP_OK);
    }


public function store(Request $request)
{
   try {

        $validated = $request->validate([
            'nama_produk' => 'required|string|max:255',
            'kategori_produk' => 'required|string|max:255',
            'jenis_produk' => 'required|string|max:255',
            'product_group' => 'nullable|string|max:255',
            'ld_s' => 'nullable|string|max:255',
            'ld_m' => 'nullable|string|max:255',
            'ld_l' => 'nullable|string|max:255',
            'ld_xl' => 'nullable|string|max:255',
            'pj_dress' => 'nullable|numeric|min:0',
            'pj_celana' => 'nullable|numeric|min:0',
            'pj_baju' => 'nullable|numeric|min:0',
            'gambar_produk' => 'nullable|image|mimes:jpeg,png,jpg|max:25000',

            'warna' => 'nullable|array|min:1',
            'warna.*' => 'required|string|max:50',

            'ukuran' => 'nullable|array|min:1',
            'ukuran.*' => 'required|string|max:50',
            'sku_items' => 'nullable|array',
            'sku_items.*.sku' => 'nullable|string|max:255',
            'sku_items.*.warna' => 'nullable|string|max:50',
            'sku_items.*.ukuran' => 'nullable|string|max:50',

            'komponen' => 'nullable|array',
            'komponen.*.jenis_komponen' => 'required|string',
            'komponen.*.sumber_komponen' => 'required|in:bahan,aksesoris',
            'komponen.*.bahan_id' => 'nullable|required_if:komponen.*.sumber_komponen,bahan|exists:bahan,id',
            'komponen.*.aksesoris_id' => 'nullable|required_if:komponen.*.sumber_komponen,aksesoris|exists:aksesoris,id',
            'komponen.*.jumlah_bahan' => 'required|numeric|min:0.0001',

            'harga_jasa_cutting' => 'nullable|numeric',
            'harga_jasa_cmt' => 'nullable|numeric',
            'harga_jasa_aksesoris' => 'nullable|numeric',
            'harga_overhead' => 'nullable|numeric',
        ]);

    } catch (ValidationException $e) {

        return response()->json([
            'message' => 'Validasi gagal',
            'errors' => $e->errors()
        ], 422);
    }
    
    $normalizedWarna = $this->normalizeAttributeList($request->input('warna', []));
    $normalizedUkuran = $this->normalizeAttributeList($request->input('ukuran', []));
    $catalog = $this->resolveProductGroupCatalog($validated['product_group'] ?? null);

    if (($validated['product_group'] ?? '') !== '' && $catalog === null) {
        return response()->json([
            'message' => 'Product group tidak ditemukan pada Product List.'
        ], 422);
    }

    if ($catalog !== null) {
        $validated = $this->applyProductGroupCatalog($validated, $catalog);
    }

    $skuItems = $catalog['sku_items'] ?? $this->buildManualSkuItems($normalizedWarna, $normalizedUkuran);

    if (empty($skuItems)) {
        return response()->json([
            'message' => 'SKU wajib diisi dari Product List atau kombinasi warna dan ukuran.'
        ], 422);
    }


    // upload gambar
    if ($request->hasFile('gambar_produk')) {
        $file = $request->file('gambar_produk');
        $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file->getClientOriginalName());
        $file->storeAs('public/images', $fileName);
        $validated['gambar_produk'] = 'images/' . $fileName;
    }

    $validated['status_produk'] = 'Sementara';

    $produkId = null;
    DB::transaction(function () use ($validated, $request, $skuItems, &$produkId) {

        // 1️⃣ CREATE PRODUK
        $produk = Produk::create($validated);
        $produkId = $produk->id;

        // 2️⃣ CREATE SKU (AUTO SILANG WARNA × UKURAN)
        $this->syncProdukSkus($produk, $skuItems);

        // 3️⃣ KOMPONEN & HITUNG HPP
        $totalKomponen = 0;

        if ($request->filled('komponen')) {
            foreach ($request->komponen as $komp) {

                $hargaSnapshot = $komp['sumber_komponen'] === 'bahan'
                    ? Bahan::findOrFail($komp['bahan_id'])->harga
                    : Aksesoris::findOrFail($komp['aksesoris_id'])->harga_per_biji;

                $total = $hargaSnapshot * $komp['jumlah_bahan'];

                $produk->komponen()->create([
                    'jenis_komponen' => $komp['jenis_komponen'],
                    'sumber_komponen' => $komp['sumber_komponen'],
                    'bahan_id' => $komp['sumber_komponen'] === 'bahan' ? $komp['bahan_id'] : null,
                    'aksesoris_id' => $komp['sumber_komponen'] === 'aksesoris' ? $komp['aksesoris_id'] : null,
                    'harga_bahan' => $hargaSnapshot,
                    'jumlah_bahan' => $komp['jumlah_bahan'],
                    'total_harga_bahan' => $total,
                ]);

                $totalKomponen += $total;
            }
        }

        // 4️⃣ UPDATE HPP
        $hpp = $totalKomponen
            + ($produk->harga_jasa_cutting ?? 0)
            + ($produk->harga_jasa_cmt ?? 0)
            + ($produk->harga_jasa_aksesoris ?? 0)
            + ($produk->harga_overhead ?? 0);

        $produk->update(['hpp' => $hpp]);
    });

    // Load produk dengan relasi setelah transaction
    $produk = Produk::with(['skus', 'komponen'])->findOrFail($produkId);

    return response()->json(
        $produk,
        Response::HTTP_CREATED
    );
}

  
    public function show(Produk $produk)
    {
        return response()->json($produk->load(['komponen.bahan', 'komponen.aksesoris', 'skus']), Response::HTTP_OK);
    }

    
    public function update(Request $request, $id)
{
    $validated = $request->validate([
        'nama_produk' => 'required|string|max:255',
        'kategori_produk' => 'required|string|max:255',
        'jenis_produk' => 'required|string|max:255',
        'product_group' => 'nullable|string|max:255',
        'ld_s' => 'nullable|string|max:255',
        'ld_m' => 'nullable|string|max:255',
        'ld_l' => 'nullable|string|max:255',
        'ld_xl' => 'nullable|string|max:255',
        'pj_dress' => 'nullable|numeric|min:0',
        'pj_celana' => 'nullable|numeric|min:0',
        'pj_baju' => 'nullable|numeric|min:0',
        'status_produk' => 'nullable|string',
        'gambar_produk' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:25000',
        'warna' => 'nullable|array|min:1',
        'warna.*' => 'required|string|max:50',
        'ukuran' => 'nullable|array|min:1',
        'ukuran.*' => 'required|string|max:50',
        'sku_items' => 'nullable|array',
        'sku_items.*.sku' => 'nullable|string|max:255',
        'sku_items.*.warna' => 'nullable|string|max:50',
        'sku_items.*.ukuran' => 'nullable|string|max:50',

        'komponen' => 'array',
        'komponen.*.jenis_komponen' => 'required|string',
        'komponen.*.sumber_komponen' => 'required|in:bahan,aksesoris',
        'komponen.*.bahan_id' => 'nullable|required_if:komponen.*.sumber_komponen,bahan|exists:bahan,id',
        'komponen.*.aksesoris_id' => 'nullable|required_if:komponen.*.sumber_komponen,aksesoris|exists:aksesoris,id',
        'komponen.*.jumlah_bahan' => 'required|numeric|min:0.0001',

        'harga_jasa_cutting' => 'nullable|numeric',
        'harga_jasa_cmt' => 'nullable|numeric',
        'harga_jasa_aksesoris' => 'nullable|numeric',
        'harga_overhead' => 'nullable|numeric',
    ]);

    $catalog = $this->resolveProductGroupCatalog($validated['product_group'] ?? null);

    if (($validated['product_group'] ?? '') !== '' && $catalog === null) {
        return response()->json([
            'message' => 'Product group tidak ditemukan pada Product List.'
        ], 422);
    }

    if ($catalog !== null) {
        $validated = $this->applyProductGroupCatalog($validated, $catalog);
    }

    $skuItems = $catalog['sku_items'] ?? [];

    if (empty($skuItems) && is_array($request->warna) && is_array($request->ukuran)) {
        $skuItems = $this->buildManualSkuItems(
            $this->normalizeAttributeList($request->warna),
            $this->normalizeAttributeList($request->ukuran)
        );
    }

    if (($validated['product_group'] ?? '') !== '' && empty($skuItems)) {
        return response()->json([
            'message' => 'Product group ini belum memiliki SKU pada Product List.'
        ], 422);
    }

    $produk = Produk::with('komponen')->findOrFail($id);

    // 🔹 SNAPSHOT DATA LAMA
    $oldData = [
        'produk' => $produk->toArray(),
        'komponen' => $produk->komponen->toArray()
    ];

    // 🔹 GANTI GAMBAR JIKA ADA
    if ($request->hasFile('gambar_produk')) {
        if ($produk->gambar_produk && Storage::exists('public/' . $produk->gambar_produk)) {
            Storage::delete('public/' . $produk->gambar_produk);
        }

        $file = $request->file('gambar_produk');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs('public/images', $fileName);
        $validated['gambar_produk'] = 'images/' . $fileName;
    }

    DB::transaction(function () use ($validated, $request, $produk, $oldData, $skuItems) {

        // update produk utama
        $produk->update($validated);

        if (!empty($skuItems)) {
            $this->syncProdukSkus($produk, $skuItems);
        }

        // hapus komponen lama
        $produk->komponen()->delete();

        $totalKomponen = 0;

        foreach ($request->komponen ?? [] as $komp) {

            if ($komp['sumber_komponen'] === 'bahan') {
                $hargaSnapshot = Bahan::findOrFail($komp['bahan_id'])->harga;
            } else {
                $hargaSnapshot = Aksesoris::findOrFail($komp['aksesoris_id'])->harga_per_biji;
            }

            $total = $hargaSnapshot * $komp['jumlah_bahan'];

            $produk->komponen()->create([
                'jenis_komponen' => $komp['jenis_komponen'],
                'sumber_komponen' => $komp['sumber_komponen'],
                'bahan_id' => $komp['sumber_komponen'] === 'bahan' ? $komp['bahan_id'] : null,
                'aksesoris_id' => $komp['sumber_komponen'] === 'aksesoris' ? $komp['aksesoris_id'] : null,
                'harga_bahan' => $hargaSnapshot,
                'jumlah_bahan' => $komp['jumlah_bahan'],
                'total_harga_bahan' => $total,
            ]);

            $totalKomponen += $total;
        }

        // hitung ulang HPP
        $hpp = $totalKomponen
            + ($produk->harga_jasa_cutting ?? 0)
            + ($produk->harga_jasa_cmt ?? 0)
            + ($produk->harga_jasa_aksesoris ?? 0)
            + ($produk->harga_overhead ?? 0);

        $produk->update(['hpp' => $hpp]);

        // 🔹 SNAPSHOT DATA BARU
        $newData = [
            'produk' => $produk->fresh()->toArray(),
            'komponen' => $produk->komponen()->get()->toArray()
        ];

        // 🔥 SIMPAN HISTORY
        ProdukUpdateHistory::create([
            'produk_id' => $produk->id,
            'user_id' => auth()->id(),
            'action' => 'update',
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    });

    return response()->json(
        $produk->load(['komponen', 'skus']),
        Response::HTTP_OK
    );
}

private function normalizeAttributeList(array $values): array
{
    $seen = [];
    $result = [];

    foreach ($values as $value) {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            continue;
        }

        $key = mb_strtoupper($trimmed);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $result[] = $trimmed;
    }

    return $result;
}

private function resolveProductGroupCatalog($productGroup): ?array
{
    $group = trim((string) ($productGroup ?? ''));

    if ($group === '') {
        return null;
    }

    $rows = ProductList::query()
        ->where('product_group', $group)
        ->orderBy('id')
        ->get([
            'product_group',
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
        return null;
    }

    [$jenisProduk, $namaProduk] = $this->splitProductGroup($group);

    return [
        'product_group' => $group,
        'jenis_produk' => $jenisProduk,
        'nama_produk' => $namaProduk,
        'ld_s' => $this->firstFilledProductListValue($rows, 'id_s'),
        'ld_m' => $this->firstFilledProductListValue($rows, 'id_m'),
        'ld_l' => $this->firstFilledProductListValue($rows, 'id_l'),
        'ld_xl' => $this->firstFilledProductListValue($rows, 'id_xl'),
        'pj_dress' => $this->firstFilledProductListValue($rows, 'pj_dress'),
        'pj_celana' => $this->firstFilledProductListValue($rows, 'pj_celana'),
        'pj_baju' => $this->firstFilledProductListValue($rows, 'pj_baju'),
        'harga_jasa_cmt' => $this->firstFilledProductListValue($rows, 'price_cmt'),
        'harga_jasa_cutting' => $this->firstFilledProductListValue($rows, 'price_cutting'),
        'sku_items' => $rows
            ->filter(function ($row) {
                return trim((string) ($row->sku_name ?? '')) !== '';
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
            ->all(),
    ];
}

private function applyProductGroupCatalog(array $validated, array $catalog): array
{
    foreach (['product_group', 'jenis_produk', 'nama_produk', 'ld_s', 'ld_m', 'ld_l', 'ld_xl', 'pj_dress', 'pj_celana', 'pj_baju'] as $field) {
        $validated[$field] = $catalog[$field] ?? null;
    }

    foreach (['harga_jasa_cutting', 'harga_jasa_cmt'] as $field) {
        if ($this->isBlank($validated[$field] ?? null) && !$this->isBlank($catalog[$field] ?? null)) {
            $validated[$field] = $catalog[$field];
        }
    }

    return $validated;
}

private function buildManualSkuItems(array $warnaList, array $ukuranList): array
{
    if (empty($warnaList) || empty($ukuranList)) {
        return [];
    }

    $skuItems = [];

    foreach ($warnaList as $warna) {
        foreach ($ukuranList as $ukuran) {
            $skuItems[] = [
                'sku' => '',
                'warna' => $warna,
                'ukuran' => $ukuran,
            ];
        }
    }

    return $skuItems;
}

private function syncProdukSkus(Produk $produk, array $skuItems): void
{
    foreach ($skuItems as $item) {
        $sku = trim((string) ($item['sku'] ?? ''));
        $warna = trim((string) ($item['warna'] ?? ''));
        $ukuran = trim((string) ($item['ukuran'] ?? ''));

        if ($sku !== '') {
            $duplicate = ProdukSku::query()
                ->where('sku', $sku)
                ->where('produk_id', '<>', $produk->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'product_group' => "SKU {$sku} sudah dipakai oleh produk lain.",
                ]);
            }

            $produk->skus()->updateOrCreate(
                ['sku' => $sku],
                [
                    'warna' => $warna !== '' ? $warna : $sku,
                    'ukuran' => $ukuran !== '' ? $ukuran : $sku,
                ]
            );

            continue;
        }

        if ($warna === '' || $ukuran === '') {
            continue;
        }

        $produk->skus()->updateOrCreate(
            [
                'warna' => $warna,
                'ukuran' => $ukuran,
            ],
            []
        );
    }
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

private function firstFilledProductListValue($rows, string $key)
{
    foreach ($rows as $row) {
        $value = $row->{$key} ?? null;

        if (!$this->isBlank($value)) {
            return $value;
        }
    }

    return null;
}

private function isBlank($value): bool
{
    return $value === null || (is_string($value) && trim($value) === '');
}


public function histories($id)
{
    $histories = ProdukUpdateHistory::with('user')
        ->where('produk_id', $id)
        ->latest()
        ->get();

    return response()->json($histories);
}

    
    public function destroy(Produk $produk)
    {
        // Hapus gambar dari storage jika ada
        if ($produk->gambar_produk && Storage::exists('public/' . $produk->gambar_produk)) {
            Storage::delete('public/' . $produk->gambar_produk);
        }
    
        // Hapus data produk dari database
        $produk->delete();
    
        return response()->json(['message' => 'Produk berhasil dihapus'], Response::HTTP_OK);
    }
    
    /**
     * Download PDF untuk produk
     */
    public function downloadPdf($id)
    {
        $produk = Produk::with(['komponen.bahan', 'komponen.aksesoris'])->findOrFail($id);

        // Hitung total komponen
        $totalKomponen = $produk->komponen->sum('total_harga_bahan');

        // Konversi gambar ke base64 jika ada
        $gambarBase64 = null;
        if ($produk->gambar_produk) {
            $gambarPath = storage_path('app/public/' . $produk->gambar_produk);
            if (file_exists($gambarPath)) {
                $gambarData = file_get_contents($gambarPath);
                $gambarBase64 = 'data:' . mime_content_type($gambarPath) . ';base64,' . base64_encode($gambarData);
            }
        }

        // Format data untuk PDF
        $data = [
            'produk' => $produk,
            'totalKomponen' => $totalKomponen,
            'tanggal' => now()->format('d F Y'),
            'waktu' => now()->format('H:i:s'),
            'gambarBase64' => $gambarBase64,
        ];

        // Generate PDF
        $pdf = Pdf::loadView('produk.pdf', $data);

        // Set nama file
        $fileName = 'Produk_' . str_replace(' ', '_', $produk->nama_produk) . '_' . date('Y-m-d') . '.pdf';

        // Download PDF
        return $pdf->download($fileName);
    }
}
