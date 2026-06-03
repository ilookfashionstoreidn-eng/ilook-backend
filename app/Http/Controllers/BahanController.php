<?php

namespace App\Http\Controllers;

use App\Models\Bahan;
use App\Models\BahanImage;
use App\Models\Pabrik;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BahanController extends Controller
{
    private const UNKNOWN_PABRIK = '-';
    private const EMPTY_WARNA_KEY = '__tanpa_warna__';

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'all' => 'nullable|boolean',
            'search' => 'nullable|string|max:100',
            'group_bahan' => 'nullable|string|max:255',
            'pabrik_bahan' => 'nullable|string|max:255',
            'satuan' => 'nullable|string|max:50',
        ]);

        $perPage = max(1, min((int) ($validated['per_page'] ?? 25), 100));
        $search = trim((string) ($validated['search'] ?? ''));

        $query = Bahan::query()->with('bahanImage');

        if ($search !== '') {
            $searchPattern = '%' . $this->escapeLike($search) . '%';

            $query->where(function ($nested) use ($search, $searchPattern) {
                if (ctype_digit($search)) {
                    $nested->orWhere('id', (int) $search);
                }

                $nested->orWhere('nama_bahan', 'like', $searchPattern)
                    ->orWhere('deskripsi', 'like', $searchPattern)
                    ->orWhere('satuan', 'like', $searchPattern);

                foreach (['group_bahan', 'pabrik_bahan', 'warna_bahan'] as $column) {
                    if ($this->hasBahanColumn($column)) {
                        $nested->orWhere($column, 'like', $searchPattern);
                    }
                }
            });
        }

        if (!empty($validated['group_bahan']) && $this->hasBahanColumn('group_bahan')) {
            $query->where('group_bahan', $validated['group_bahan']);
        }

        if (!empty($validated['pabrik_bahan']) && $this->hasBahanColumn('pabrik_bahan')) {
            $query->where('pabrik_bahan', $validated['pabrik_bahan']);
        }

        if (!empty($validated['satuan'])) {
            $query->where('satuan', $validated['satuan']);
        }

        $filteredQuery = clone $query;

        if ($request->boolean('all')) {
            $items = $query
                ->orderBy('nama_bahan')
                ->orderBy('id')
                ->get();

            return response()->json([
                'data' => $items,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $items->count(),
                'total' => $items->count(),
                'from' => $items->isEmpty() ? null : 1,
                'to' => $items->count(),
                'stats' => $this->stats($filteredQuery),
                'filters' => [
                    'groups' => $this->distinctOptions('group_bahan'),
                    'pabriks' => $this->masterPabrikOptions(),
                ],
            ]);
        }

        $paginated = $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
            'stats' => $this->stats($filteredQuery),
            'filters' => [
                'groups' => $this->distinctOptions('group_bahan'),
                'pabriks' => $this->masterPabrikOptions(),
            ],
        ]);
    }

    public function listSummary(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $bahanRows = $this->bahanListRows($search);
        $orderedTotals = $this->bahanListOrderedTotals();
        $groups = $this->buildBahanListSummary($bahanRows, $orderedTotals);

        return response()->json([
            'success' => true,
            'data' => $groups,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'total_group' => count($groups),
                'total_warna' => array_sum(array_map(fn ($group) => (int) ($group['total_warna'] ?? 0), $groups)),
            ],
        ]);
    }

    public function downloadListSummaryPdf(Request $request)
    {
        $validated = $request->validate([
            'group_key' => 'required|string|max:255',
        ]);

        $selectedKey = $this->normalizeBahanListKey($validated['group_key']);
        $groups = $this->buildBahanListSummary($this->bahanListRows(), $this->bahanListOrderedTotals());
        $group = collect($groups)->first(fn ($item) => ($item['key'] ?? '') === $selectedKey);

        if (!$group) {
            return response()->json([
                'success' => false,
                'message' => 'Group bahan tidak ditemukan.',
            ], 404);
        }

        $rows = collect($group['rows'] ?? [])
            ->values()
            ->map(function ($row, $index) {
                $grandTotal = (float) ($row['grand_total'] ?? 0);

                return [
                    'no' => $index + 1,
                    'warna' => mb_strtoupper((string) ($row['warna'] ?? '-')),
                    'stok_gudang' => $this->formatBahanListPdfRoll($row['stok_gudang'] ?? 0),
                    'dipesan' => $this->formatBahanListPdfRoll($row['dipesan'] ?? 0),
                    'grand_total' => $this->formatBahanListPdfRoll($grandTotal),
                    'grand_total_tone' => $this->bahanListGrandTotalTone($grandTotal),
                ];
            })
            ->all();

        $previewRow = collect($group['rows'] ?? [])->first(fn ($row) => !empty($row['image_path']));
        $printedAt = now('Asia/Jakarta');
        $fileName = 'Bahan-List-' . $this->safeBahanListFileName($group['group_bahan'] ?? 'bahan') . '-' . $printedAt->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('pdf.bahan-list', [
            'group' => $group,
            'rows' => $rows,
            'totals' => [
                'stok_gudang' => $this->formatBahanListPdfRoll($group['total_stok_gudang'] ?? 0),
                'dipesan' => $this->formatBahanListPdfRoll($group['total_dipesan'] ?? 0),
                'grand_total' => $this->formatBahanListPdfRoll($group['total_grand_total'] ?? 0),
                'grand_total_tone' => $this->bahanListGrandTotalTone($group['total_grand_total'] ?? 0),
            ],
            'imageDataUri' => $this->bahanImageDataUri($previewRow['image_path'] ?? null),
            'printedAt' => $printedAt->format('d/m/Y H:i:s'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($fileName);
    }

    private function bahanListRows(string $search = '')
    {
        $query = Bahan::query()
            ->select([
                'bahan.id',
                'bahan.group_bahan',
                'bahan.pabrik_bahan',
                'bahan.nama_bahan',
                'bahan.warna_bahan',
                'bahan.stok_bahan',
            ]);

        if ($this->hasBahanColumn('bahan_image_id') && Schema::hasTable('bahan_images')) {
            $query
                ->leftJoin('bahan_images', 'bahan_images.id', '=', 'bahan.bahan_image_id')
                ->addSelect('bahan_images.image_path');
        } else {
            $query->addSelect(DB::raw('NULL as image_path'));
        }

        if ($search !== '') {
            $query->where('bahan.nama_bahan', 'like', '%' . $this->escapeLike($search) . '%');
        }

        return $query
            ->orderBy('bahan.group_bahan')
            ->orderBy('bahan.warna_bahan')
            ->orderBy('bahan.nama_bahan')
            ->get();
    }

    private function bahanListOrderedTotals(): array
    {
        $totals = [];

        DB::table('spk_bahan')
            ->join('bahan', 'bahan.id', '=', 'spk_bahan.bahan_id')
            ->join('spk_bahan_warna', 'spk_bahan_warna.spk_bahan_id', '=', 'spk_bahan.id')
            ->select([
                'bahan.group_bahan',
                'bahan.nama_bahan',
                'spk_bahan_warna.warna',
            ])
            ->selectRaw('COALESCE(SUM(spk_bahan_warna.jumlah_rol), 0) as total_dipesan')
            ->groupBy('bahan.group_bahan', 'bahan.nama_bahan', 'spk_bahan_warna.warna')
            ->get()
            ->each(function ($row) use (&$totals) {
                $this->appendBahanListOrderedTotal(
                    $totals,
                    $row->group_bahan ?: $row->nama_bahan,
                    $row->warna,
                    $row->total_dipesan
                );
            });

        DB::table('spk_bahan')
            ->join('bahan', 'bahan.id', '=', 'spk_bahan.bahan_id')
            ->leftJoin('spk_bahan_warna', 'spk_bahan_warna.spk_bahan_id', '=', 'spk_bahan.id')
            ->whereNull('spk_bahan_warna.id')
            ->select([
                'bahan.group_bahan',
                'bahan.nama_bahan',
                'bahan.warna_bahan as warna',
            ])
            ->selectRaw('COALESCE(SUM(spk_bahan.jumlah), 0) as total_dipesan')
            ->groupBy('bahan.group_bahan', 'bahan.nama_bahan', 'bahan.warna_bahan')
            ->get()
            ->each(function ($row) use (&$totals) {
                $this->appendBahanListOrderedTotal(
                    $totals,
                    $row->group_bahan ?: $row->nama_bahan,
                    $row->warna,
                    $row->total_dipesan
                );
            });

        return $totals;
    }

    private function buildBahanListSummary($bahanRows, array $orderedTotals): array
    {
        $materialMap = [];

        foreach ($bahanRows as $row) {
            $groupBahan = trim((string) ($row->group_bahan ?: $row->nama_bahan));
            if ($groupBahan === '') {
                continue;
            }

            $materialKey = $this->normalizeBahanListKey($groupBahan);
            if (!isset($materialMap[$materialKey])) {
                $materialMap[$materialKey] = [
                    'key' => $materialKey,
                    'group_bahan' => $groupBahan,
                    'nama_bahan_list' => [],
                    'group_bahan_list' => [],
                    'pabrik_bahan_list' => [],
                    'rows' => [],
                    '_warna_order' => [],
                ];
            }

            $warna = trim((string) ($row->warna_bahan ?: 'Tanpa Warna')) ?: 'Tanpa Warna';
            $warnaKey = $this->normalizeBahanListWarnaKey($warna);

            if (!isset($materialMap[$materialKey]['rows'][$warnaKey])) {
                $materialMap[$materialKey]['rows'][$warnaKey] = [
                    'key' => $warnaKey,
                    'warna' => $warna,
                    'stok_gudang' => 0,
                    'dipesan' => 0,
                    'grand_total' => 0,
                    'image_url' => '',
                    'image_path' => '',
                    'group_bahan_list' => [],
                    'pabrik_bahan_list' => [],
                ];
                $materialMap[$materialKey]['_warna_order'][] = $warnaKey;
            }

            $materialMap[$materialKey]['nama_bahan_list'] = $this->appendUniqueValue($materialMap[$materialKey]['nama_bahan_list'], $row->nama_bahan);
            $materialMap[$materialKey]['group_bahan_list'] = $this->appendUniqueValue($materialMap[$materialKey]['group_bahan_list'], $row->group_bahan);
            $materialMap[$materialKey]['pabrik_bahan_list'] = $this->appendUniqueValue($materialMap[$materialKey]['pabrik_bahan_list'], $row->pabrik_bahan);

            $materialMap[$materialKey]['rows'][$warnaKey]['stok_gudang'] += (float) ($row->stok_bahan ?? 0);
            $materialMap[$materialKey]['rows'][$warnaKey]['group_bahan_list'] = $this->appendUniqueValue(
                $materialMap[$materialKey]['rows'][$warnaKey]['group_bahan_list'],
                $row->group_bahan
            );
            $materialMap[$materialKey]['rows'][$warnaKey]['pabrik_bahan_list'] = $this->appendUniqueValue(
                $materialMap[$materialKey]['rows'][$warnaKey]['pabrik_bahan_list'],
                $row->pabrik_bahan
            );

            if (empty($materialMap[$materialKey]['rows'][$warnaKey]['image_path']) && !empty($row->image_path)) {
                $materialMap[$materialKey]['rows'][$warnaKey]['image_path'] = $row->image_path;
                $materialMap[$materialKey]['rows'][$warnaKey]['image_url'] = $this->bahanImageUrl($row->image_path);
            }
        }

        $groups = [];
        foreach ($materialMap as $materialKey => $material) {
            $rows = [];
            foreach ($material['_warna_order'] as $warnaKey) {
                $row = $material['rows'][$warnaKey];
                $row['dipesan'] = (float) ($orderedTotals[$materialKey][$warnaKey] ?? 0);
                $row['grand_total'] = $row['stok_gudang'] + $row['dipesan'];
                $rows[] = $row;
            }

            usort($rows, fn ($a, $b) => strnatcasecmp($a['warna'], $b['warna']));

            $totalStok = array_sum(array_column($rows, 'stok_gudang'));
            $totalDipesan = array_sum(array_column($rows, 'dipesan'));
            unset($material['rows'], $material['_warna_order']);

            $groups[] = array_merge($material, [
                'rows' => $rows,
                'total_warna' => count($rows),
                'total_stok_gudang' => $totalStok,
                'total_dipesan' => $totalDipesan,
                'total_grand_total' => $totalStok + $totalDipesan,
            ]);
        }

        usort($groups, fn ($a, $b) => strnatcasecmp($a['group_bahan'], $b['group_bahan']));

        return $groups;
    }

    private function appendBahanListOrderedTotal(array &$totals, $groupBahan, $warna, $value): void
    {
        $groupKey = $this->normalizeBahanListKey($groupBahan);
        if ($groupKey === '') {
            return;
        }

        $warnaKey = $this->normalizeBahanListWarnaKey($warna);
        $totals[$groupKey][$warnaKey] = ($totals[$groupKey][$warnaKey] ?? 0) + (float) $value;
    }

    private function appendUniqueValue(array $values, $value): array
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $values;
        }

        foreach ($values as $existing) {
            if ($this->normalizeBahanListKey($existing) === $this->normalizeBahanListKey($text)) {
                return $values;
            }
        }

        $values[] = $text;
        return $values;
    }

    private function bahanImageUrl(?string $imagePath): string
    {
        $filename = basename((string) $imagePath);
        return $filename !== '' ? url('/api/bahan-images/' . rawurlencode($filename)) : '';
    }

    private function bahanImageDataUri(?string $imagePath): ?string
    {
        if (!$imagePath) {
            return null;
        }

        $normalizedPath = ltrim(str_replace(['public/', 'storage/'], '', $imagePath), '/\\');
        $candidatePaths = array_filter([
            Storage::disk('public')->exists($normalizedPath) ? Storage::disk('public')->path($normalizedPath) : null,
            storage_path('app/public/' . $normalizedPath),
            public_path('storage/' . $normalizedPath),
        ]);

        foreach (array_unique($candidatePaths) as $candidatePath) {
            if (is_file($candidatePath) && is_readable($candidatePath)) {
                $mimeType = mime_content_type($candidatePath) ?: 'image/jpeg';
                return 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($candidatePath));
            }
        }

        return null;
    }

    private function formatBahanListPdfRoll($value): string
    {
        $number = (float) ($value ?? 0);
        $formatted = floor($number) === $number
            ? number_format($number, 0, ',', '.')
            : rtrim(rtrim(number_format($number, 2, ',', '.'), '0'), ',');

        return $formatted . ' - ROL';
    }

    private function bahanListGrandTotalTone($value): string
    {
        $number = (float) ($value ?? 0);

        if ($number == 0.0) {
            return 'grand-zero';
        }

        if ($number < 10) {
            return 'grand-low';
        }

        if ($number > 10) {
            return 'grand-high';
        }

        return 'grand-neutral';
    }

    private function safeBahanListFileName($value): string
    {
        $fileName = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim((string) $value));
        $fileName = trim((string) $fileName, '-');

        return $fileName !== '' ? $fileName : 'bahan';
    }

    private function normalizeBahanListWarnaKey($value): string
    {
        $key = $this->normalizeBahanListKey($value);
        return $key !== '' ? $key : self::EMPTY_WARNA_KEY;
    }

    private function normalizeBahanListKey($value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) ($value ?? '')));
        return mb_strtolower($normalized);
    }

    public function store(Request $request)
    {  
        $validated = $request->validate($this->validationRules($request));
        $validated = $this->normalizePayload($validated);
        $bahan = Bahan::create($this->filterExistingColumns($validated));
        return response()->json($bahan, 201);
    }

    public function storeImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:2048',
            'bahan_ids' => 'required|array|min:1',
            'bahan_ids.*' => 'required|integer|distinct|exists:bahan,id',
        ]);

        $imagePath = Storage::disk('public')->putFile('bahan-images', $request->file('image'));

        if (!$imagePath) {
            return response()->json([
                'message' => 'Gagal menyimpan file gambar bahan.',
            ], 500);
        }

        try {
            $this->mirrorPublicStorageFile($imagePath);

            $bahanImage = DB::transaction(function () use ($validated, $imagePath) {
                $bahanImage = BahanImage::create([
                    'image_path' => $imagePath,
                ]);

                Bahan::query()
                    ->whereIn('id', $validated['bahan_ids'])
                    ->update(['bahan_image_id' => $bahanImage->id]);

                return $bahanImage;
            });
        } catch (\Throwable $error) {
            Storage::disk('public')->delete($imagePath);
            $this->deleteMirroredPublicStorageFile($imagePath);
            throw $error;
        }

        $bahanImage->load(['bahans' => function ($query) {
            $query->orderBy('nama_bahan');
        }]);

        return response()->json([
            'message' => 'Gambar bahan berhasil disimpan.',
            'data' => $bahanImage,
        ], 201);
    }

    public function showImage(string $filename)
    {
        $path = 'bahan-images/' . basename($filename);

        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    public function show($id)
    {
        $bahan = Bahan::findOrFail($id);
        return response()->json($bahan);
    }

    public function update(Request $request, $id)
    {
        $bahan = Bahan::findOrFail($id);
        $oldSatuan = $bahan->satuan;

        $validated = $request->validate($this->validationRules($request, $bahan));
        $validated = $this->normalizePayload($validated);
        $bahan->update($this->filterExistingColumns($validated));

        // Sinkronisasi satuan ke semua bahan dengan nama_bahan yang sama
        if (!empty($bahan->nama_bahan) && $oldSatuan !== $bahan->satuan) {
            Bahan::where('nama_bahan', $bahan->nama_bahan)
                ->where('id', '!=', $bahan->id)
                ->update(['satuan' => $bahan->satuan]);
        }

        return response()->json($bahan);
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:5000',
            'rows.*.nama_bahan' => 'required|string|max:255',
            'rows.*.group_bahan' => 'nullable|string|max:255',
            'rows.*.pabrik_bahan' => 'nullable|string|max:255',
            'rows.*.warna_bahan' => 'nullable|string|max:255',
            'rows.*.stok_bahan' => 'nullable|numeric|min:0',
            'rows.*.deskripsi' => 'nullable|string',
            'rows.*.harga' => 'nullable|numeric|min:0',
            'rows.*.satuan' => 'nullable|string|max:50',
        ]);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, &$created, &$updated, &$skipped) {
            foreach ($validated['rows'] as $row) {
                $namaBahan = trim($row['nama_bahan'] ?? '');

                if ($namaBahan === '') {
                    $skipped++;
                    continue;
                }

                $data = [
                    'group_bahan' => $this->emptyToNull($row['group_bahan'] ?? null),
                    'pabrik_bahan' => $this->normalizePabrikBahan($row['pabrik_bahan'] ?? null),
                    'nama_bahan' => $namaBahan,
                    'warna_bahan' => $this->emptyToNull($row['warna_bahan'] ?? null),
                    'stok_bahan' => isset($row['stok_bahan']) && $row['stok_bahan'] !== '' ? (float) $row['stok_bahan'] : 0,
                    'deskripsi' => $this->emptyToNull($row['deskripsi'] ?? null),
                    'harga' => isset($row['harga']) && $row['harga'] !== '' ? (float) $row['harga'] : 0,
                    'satuan' => $this->emptyToNull($row['satuan'] ?? null) ?: 'kg',
                ];

                $data = $this->filterExistingColumns($data);
                $bahan = $this->matchingBahanQuery($data)->first();

                if ($bahan) {
                    $bahan->update($data);
                    $updated++;
                    continue;
                }

                Bahan::create($data);
                $created++;
            }
        });

        return response()->json([
            'message' => 'Import bahan selesai.',
            'summary' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'total' => count($validated['rows']),
            ],
        ]);
    }

    private function validationRules(Request $request, ?Bahan $bahan = null): array
    {
        $uniqueRule = Rule::unique('bahan', 'nama_bahan');

        if ($bahan) {
            $uniqueRule->ignore($bahan->id);
        }

        $uniqueRule->where(function ($query) use ($request) {
            foreach (['warna_bahan', 'pabrik_bahan'] as $column) {
                if ($this->hasBahanColumn($column)) {
                    $query->where(
                        $column,
                        $column === 'pabrik_bahan'
                            ? $this->normalizePabrikBahan($request->input($column))
                            : $request->input($column)
                    );
                }
            }

            return $query;
        });

        return [
            'group_bahan' => 'nullable|string|max:255',
            'pabrik_bahan' => 'nullable|string|max:255',
            'nama_bahan' => ['required', 'string', 'max:255', $uniqueRule],
            'deskripsi' => 'nullable|string',
            'harga' => 'required|numeric|min:0',
            'satuan' => 'required|string|max:50',
            'warna_bahan' => 'nullable|string|max:255',
            'stok_bahan' => 'nullable|numeric|min:0',
        ];
    }

    private function mirrorPublicStorageFile(string $path): void
    {
        $sourcePath = Storage::disk('public')->path($path);
        $targetPath = public_path('storage/' . ltrim($path, '/'));

        File::ensureDirectoryExists(dirname($targetPath));
        File::copy($sourcePath, $targetPath);
    }

    private function deleteMirroredPublicStorageFile(string $path): void
    {
        $targetPath = public_path('storage/' . ltrim($path, '/'));

        if (File::exists($targetPath)) {
            File::delete($targetPath);
        }
    }

    private function normalizePayload(array $data): array
    {
        if (array_key_exists('pabrik_bahan', $data)) {
            $data['pabrik_bahan'] = $this->normalizePabrikBahan($data['pabrik_bahan']);
        }

        return $data;
    }

    private function normalizePabrikBahan($value): string
    {
        $pabrikName = $this->emptyToNull($value);

        if ($pabrikName === null || $pabrikName === self::UNKNOWN_PABRIK || !$this->hasMasterPabrik($pabrikName)) {
            return self::UNKNOWN_PABRIK;
        }

        return $pabrikName;
    }

    private function hasMasterPabrik(string $pabrikName): bool
    {
        static $cache = [];
        $key = strtolower(trim($pabrikName));

        if (!array_key_exists($key, $cache)) {
            $cache[$key] = Pabrik::query()
                ->whereRaw('LOWER(TRIM(nama_pabrik)) = ?', [$key])
                ->exists();
        }

        return $cache[$key];
    }

    private function matchingBahanQuery(array $data)
    {
        $query = Bahan::query()->where('nama_bahan', $data['nama_bahan']);

        foreach (['warna_bahan', 'pabrik_bahan'] as $column) {
            if ($this->hasBahanColumn($column) && array_key_exists($column, $data)) {
                $query->where($column, $data[$column]);
            }
        }

        return $query;
    }

    private function filterExistingColumns(array $data): array
    {
        foreach (['group_bahan', 'pabrik_bahan', 'warna_bahan', 'stok_bahan'] as $column) {
            if (!$this->hasBahanColumn($column)) {
                unset($data[$column]);
            }
        }

        return $data;
    }

    private function stats($query): array
    {
        return [
            'total_bahan' => (clone $query)->count(),
            'total_group' => $this->hasBahanColumn('group_bahan')
                ? (clone $query)->whereNotNull('group_bahan')->where('group_bahan', '<>', '')->distinct()->count('group_bahan')
                : 0,
            'total_warna' => $this->hasBahanColumn('warna_bahan')
                ? (clone $query)->whereNotNull('warna_bahan')->where('warna_bahan', '<>', '')->distinct()->count('warna_bahan')
                : 0,
            'total_stok' => $this->hasBahanColumn('stok_bahan') ? (float) (clone $query)->sum('stok_bahan') : 0,
        ];
    }

    private function distinctOptions(string $column): array
    {
        if (!$this->hasBahanColumn($column)) {
            return [];
        }

        return Bahan::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->limit(500)
            ->pluck($column)
            ->values()
            ->all();
    }

    private function masterPabrikOptions(): array
    {
        $options = Pabrik::query()
            ->whereNotNull('nama_pabrik')
            ->where('nama_pabrik', '<>', '')
            ->orderBy('nama_pabrik')
            ->limit(500)
            ->pluck('nama_pabrik')
            ->values()
            ->all();

        array_unshift($options, self::UNKNOWN_PABRIK);

        return array_values(array_unique($options));
    }

    private function hasBahanColumn(string $column): bool
    {
        static $columns = [];

        if (!array_key_exists($column, $columns)) {
            $columns[$column] = Schema::hasColumn('bahan', $column);
        }

        return $columns[$column];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    private function emptyToNull($value)
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    public function destroy($id)
    {
        $bahan = Bahan::findOrFail($id);

        try {
            $bahan->delete();

            return response()->json(['message' => 'Bahan berhasil dihapus dari master bahan.']);
        } catch (QueryException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }

            return response()->json([
                'code' => 'BAHAN_SEDANG_DIGUNAKAN',
                'title' => 'Bahan Tidak Dapat Dihapus',
                'message' => 'Data bahan ini sudah terhubung dengan transaksi operasional, sehingga tidak bisa dihapus dari master.',
                'detail' => 'Untuk menjaga histori ERP tetap valid, bahan yang pernah dipakai pada pembelian, SPK, stok, atau komponen produk harus tetap tersedia sebagai referensi.',
                'usage' => $this->bahanUsageSummary($bahan->id),
            ], 409);
        }
    }

    private function bahanUsageSummary(int $bahanId): array
    {
        $references = [
            ['table' => 'pembelian_bahan', 'column' => 'bahan_id', 'label' => 'Pembelian Bahan'],
            ['table' => 'spk_bahan', 'column' => 'bahan_id', 'label' => 'SPK Pemesanan Bahan'],
            ['table' => 'produk_komponen', 'column' => 'bahan_id', 'label' => 'Komponen Produk'],
            ['table' => 'spk_cutting_bahan', 'column' => 'bahan_id', 'label' => 'SPK Cutting'],
        ];

        $summary = [];

        foreach ($references as $reference) {
            if (!Schema::hasTable($reference['table']) || !Schema::hasColumn($reference['table'], $reference['column'])) {
                continue;
            }

            $count = DB::table($reference['table'])
                ->where($reference['column'], $bahanId)
                ->count();

            if ($count > 0) {
                $summary[] = [
                    'module' => $reference['label'],
                    'count' => $count,
                ];
            }
        }

        return $summary;
    }
}
