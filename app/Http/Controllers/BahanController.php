<?php

namespace App\Http\Controllers;

use App\Models\Bahan;
use App\Models\Pabrik;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class BahanController extends Controller
{
    private const UNKNOWN_PABRIK = '-';

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'group_bahan' => 'nullable|string|max:255',
            'pabrik_bahan' => 'nullable|string|max:255',
            'satuan' => 'nullable|string|max:50',
        ]);

        $perPage = max(1, min((int) ($validated['per_page'] ?? 25), 100));
        $search = trim((string) ($validated['search'] ?? ''));

        $query = Bahan::query();

        if ($search !== '') {
            $searchPrefix = $this->escapeLike($search) . '%';

            $query->where(function ($nested) use ($search, $searchPrefix) {
                if (ctype_digit($search)) {
                    $nested->orWhere('id', (int) $search);
                }

                $nested->orWhere('nama_bahan', 'like', $searchPrefix)
                    ->orWhere('deskripsi', 'like', $searchPrefix)
                    ->orWhere('satuan', 'like', $searchPrefix);

                foreach (['group_bahan', 'pabrik_bahan', 'warna_bahan'] as $column) {
                    if ($this->hasBahanColumn($column)) {
                        $nested->orWhere($column, 'like', $searchPrefix);
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

    public function store(Request $request)
    {  
        $validated = $request->validate($this->validationRules($request));
        $validated = $this->normalizePayload($validated);
        $bahan = Bahan::create($this->filterExistingColumns($validated));
        return response()->json($bahan, 201);
    }

    public function show($id)
    {
        $bahan = Bahan::findOrFail($id);
        return response()->json($bahan);
    }

    public function update(Request $request, $id)
    {
        $bahan = Bahan::findOrFail($id);
        $validated = $request->validate($this->validationRules($request, $bahan));
        $validated = $this->normalizePayload($validated);
        $bahan->update($this->filterExistingColumns($validated));
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
