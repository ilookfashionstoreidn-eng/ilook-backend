<?php

namespace App\Http\Controllers;

use App\Models\Bahan;
use App\Models\Pabrik;
use App\Models\SpkBahan;
use App\Models\SpkBahanWarna;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class SpkBahanController extends Controller
{
    public function authorizeAccess(Request $request)
    {
        $user = $request->user();
        $role = method_exists($user, 'getRoleNames') ? $user->getRoleNames()?->first() : null;

        return response()->json([
            'success' => true,
            'message' => 'Akses SPK Bahan diizinkan.',
            'data' => [
                'authorized' => true,
                'user_id' => $user?->id,
                'role' => $role,
            ],
        ]);
    }

    public function masterOptions()
    {
        $pabrikByName = [];
        $pabrikOptions = [];

        foreach (Pabrik::query()->select('id', 'nama_pabrik')->orderBy('nama_pabrik')->get() as $pabrik) {
            $row = [
                'id' => $pabrik->id,
                'nama_pabrik' => $pabrik->nama_pabrik,
            ];

            $pabrikOptions[] = $row;
            $pabrikByName[$this->normalizeKey($pabrik->nama_pabrik)] = $row;
        }

        $groups = [];
        $bahanRows = Bahan::query()
            ->select('id', 'group_bahan', 'pabrik_bahan', 'nama_bahan', 'warna_bahan')
            ->whereNotNull('group_bahan')
            ->where('group_bahan', '<>', '')
            ->orderBy('group_bahan')
            ->orderBy('nama_bahan')
            ->get();

        foreach ($bahanRows as $bahan) {
            $groupName = trim((string) $bahan->group_bahan);

            if ($groupName === '') {
                continue;
            }

            if (!isset($groups[$groupName])) {
                $groups[$groupName] = [
                    'group_bahan' => $groupName,
                    'label' => $groupName,
                    'pabrik' => [],
                    'bahan' => [],
                    'warna' => [],
                    '_pabrik_keys' => [],
                    '_warna_keys' => [],
                ];
            }

            $pabrikName = trim((string) ($bahan->pabrik_bahan ?? ''));
            $matchedPabrik = $pabrikByName[$this->normalizeKey($pabrikName)] ?? null;
            $pabrikKey = $matchedPabrik ? 'id:' . $matchedPabrik['id'] : ($pabrikName !== '' ? 'name:' . $this->normalizeKey($pabrikName) : null);

            if ($pabrikKey && !isset($groups[$groupName]['_pabrik_keys'][$pabrikKey])) {
                $groups[$groupName]['_pabrik_keys'][$pabrikKey] = true;
                $groups[$groupName]['pabrik'][] = [
                    'id' => $matchedPabrik['id'] ?? null,
                    'nama_pabrik' => $matchedPabrik['nama_pabrik'] ?? $pabrikName,
                ];
            }

            $groups[$groupName]['bahan'][] = [
                'id' => $bahan->id,
                'nama_bahan' => $bahan->nama_bahan,
                'warna_bahan' => $bahan->warna_bahan,
                'pabrik_bahan' => $pabrikName,
                'pabrik_id' => $matchedPabrik['id'] ?? null,
            ];

            $warnaName = trim((string) ($bahan->warna_bahan ?? ''));
            $warnaKey = $this->normalizeKey($warnaName);
            if ($warnaName !== '' && !isset($groups[$groupName]['_warna_keys'][$warnaKey])) {
                $groups[$groupName]['_warna_keys'][$warnaKey] = true;
                $groups[$groupName]['warna'][] = $warnaName;
            }
        }

        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return response()->json([
            'success' => true,
            'data' => [
                'groups' => array_values(array_map(function ($group) {
                    unset($group['_pabrik_keys'], $group['_warna_keys']);
                    return $group;
                }, $groups)),
                'pabrik' => $pabrikOptions,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $validated = Validator::make($request->query(), [
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'status' => 'nullable|string|max:40',
            'jenis_pembayaran' => 'nullable|string|max:40',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'sort_by' => 'nullable|in:id,tanggal_pemesanan,tanggal_jatuh_tempo,tanggal_pembayaran,created_at,jumlah',
            'sort_dir' => 'nullable|in:asc,desc',
        ])->validate();

        $perPage = (int) ($validated['per_page'] ?? 25);
        $search = trim((string) ($validated['search'] ?? ''));
        $sortBy = $validated['sort_by'] ?? 'id';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        $baseQuery = SpkBahan::query()
            ->with([
                'pabrik',
                'bahan',
                'warna',
            ])
            ->when(!empty($validated['status']), function ($query) use ($validated) {
                $query->where('status', $validated['status']);
            })
            ->when(!empty($validated['jenis_pembayaran']), function ($query) use ($validated) {
                $query->where('jenis_pembayaran', $validated['jenis_pembayaran']);
            })
            ->when(!empty($validated['tanggal_mulai']), function ($query) use ($validated) {
                $query->whereDate('tanggal_pemesanan', '>=', $validated['tanggal_mulai']);
            })
            ->when(!empty($validated['tanggal_selesai']), function ($query) use ($validated) {
                $query->whereDate('tanggal_pemesanan', '<=', $validated['tanggal_selesai']);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('status', 'like', "%{$search}%")
                        ->orWhere('jenis_pembayaran', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhereHas('pabrik', function ($pabrikQuery) use ($search) {
                            $pabrikQuery->where('nama_pabrik', 'like', "%{$search}%");
                        })
                        ->orWhereHas('bahan', function ($bahanQuery) use ($search) {
                            $bahanQuery->where('nama_bahan', 'like', "%{$search}%")
                                ->orWhere('group_bahan', 'like', "%{$search}%");
                        })
                        ->orWhereHas('warna', function ($warnaQuery) use ($search) {
                            $warnaQuery->where('warna', 'like', "%{$search}%");
                        });
                });
            });

        $paginated = (clone $baseQuery)
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->appends($request->query());

        $rows = collect($paginated->items())
            ->map(fn (SpkBahan $spkBahan) => $this->serializeSpkBahan($spkBahan))
            ->values();

        $kpiRows = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_spk')
            ->selectRaw('COUNT(DISTINCT pabrik_id) as total_pabrik_aktif')
            ->selectRaw("SUM(CASE WHEN LOWER(jenis_pembayaran) = 'tempo' THEN 1 ELSE 0 END) as total_tempo")
            ->selectRaw('COALESCE(SUM(jumlah), 0) as total_rol')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'kpi' => [
                'total_spk' => (int) ($kpiRows->total_spk ?? 0),
                'total_pabrik_aktif' => (int) ($kpiRows->total_pabrik_aktif ?? 0),
                'total_rol' => (int) ($kpiRows->total_rol ?? 0),
                'total_tempo' => (int) ($kpiRows->total_tempo ?? 0),
            ],
        ]);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_bahan' => 'required|string|max:255',
            'pabrik_id' => 'nullable|integer|exists:pabrik,id',
            'pabrik_nama' => 'nullable|string|max:255',
            'bahan_id' => 'required|integer|exists:bahan,id',
            'jenis_pembayaran' => 'required|string|max:40',
            'tanggal_pembayaran' => 'nullable|date',
            'tanggal_pemesanan' => 'required|date',
            'tanggal_jatuh_tempo' => 'nullable|date|after_or_equal:tanggal_pemesanan',
            'tempo_hari' => 'nullable|integer|min:1|max:3650',

            'warna' => 'required|array|min:1',
            'warna.*.warna' => 'required|string|max:50',
            'warna.*.jumlah_rol' => 'required|integer|min:1',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (!$request->filled('pabrik_id') && !$request->filled('pabrik_nama')) {
                $validator->errors()->add('pabrik_nama', 'Pabrik wajib diisi dari master bahan.');
            }

            if ($this->isTempoPayment($request->input('jenis_pembayaran'))) {
                if (!$request->filled('tempo_hari')) {
                    $validator->errors()->add('tempo_hari', 'Tempo pembayaran wajib diisi dalam jumlah hari.');
                }
                return;
            }
        });

        $validated = $validator->validate();
        $bahan = Bahan::findOrFail($validated['bahan_id']);
        $pabrik = $this->resolvePabrikForSpk($validated);
        $masterGroup = trim((string) ($bahan->group_bahan ?? ''));
        $requestGroup = trim((string) $validated['group_bahan']);

        if ($masterGroup === '' || $masterGroup !== $requestGroup) {
            return response()->json([
                'success' => false,
                'message' => 'Bahan tidak sesuai dengan grup bahan yang dipilih.',
                'errors' => ['bahan_id' => ['Bahan tidak sesuai dengan grup bahan yang dipilih.']],
            ], 422);
        }

        if (!$this->bahanMatchesPabrik($bahan, $pabrik)) {
            return response()->json([
                'success' => false,
                'message' => 'Pabrik tidak sesuai dengan master bahan yang dipilih.',
                'errors' => ['pabrik_id' => ['Pabrik tidak sesuai dengan master bahan yang dipilih.']],
            ], 422);
        }

        $availableWarna = $this->warnaOptionsForGroupAndPabrik($masterGroup, $pabrik->nama_pabrik);
        foreach ($validated['warna'] as $item) {
            if (!isset($availableWarna[$this->normalizeKey($item['warna'])])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warna tidak tersedia pada grup bahan yang dipilih.',
                    'errors' => ['warna' => ['Warna ' . $item['warna'] . ' tidak tersedia pada grup bahan yang dipilih.']],
                ], 422);
            }
        }

        $tanggalPemesanan = Carbon::parse($validated['tanggal_pemesanan'])->toDateString();
        $tanggalJatuhTempo = $this->isTempoPayment($validated['jenis_pembayaran'])
            ? Carbon::parse($tanggalPemesanan)->addDays((int) $validated['tempo_hari'])->toDateString()
            : null;

        DB::beginTransaction();

        try {
            // 1. Simpan header SPK Bahan (jumlah sementara 0)
            $spkBahan = SpkBahan::create([
                'pabrik_id' => $pabrik->id,
                'bahan_id' => $validated['bahan_id'],
                'jumlah' => 0, // akan diupdate
                'jenis_pembayaran' => $validated['jenis_pembayaran'],
                'tanggal_pemesanan' => $tanggalPemesanan,
                'tanggal_jatuh_tempo' => $tanggalJatuhTempo,
                'tanggal_pembayaran' => $tanggalJatuhTempo ?: $tanggalPemesanan,
                'status' => 'proses'
            ]);

            $totalJumlah = 0;

            // 2. Simpan detail warna
            foreach ($validated['warna'] as $item) {
                SpkBahanWarna::create([
                    'spk_bahan_id' => $spkBahan->id,
                    'warna' => $item['warna'],
                    'jumlah_rol' => $item['jumlah_rol'],
                ]);

                $totalJumlah += $item['jumlah_rol'];
            }

            // 3. Update total jumlah rol ke header
            $spkBahan->update([
                'jumlah' => $totalJumlah
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data SPK Bahan berhasil ditambahkan.',
                'data' => $spkBahan->load(['pabrik', 'bahan', 'warna']),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan SPK Bahan',                                                   
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function serializeSpkBahan(SpkBahan $spkBahan): array
    {
        $data = $spkBahan->toArray();
        $data['group_bahan'] = $spkBahan->bahan?->group_bahan;
        $tanggalPemesanan = $spkBahan->tanggal_pemesanan ?: ($spkBahan->created_at ? Carbon::parse($spkBahan->created_at)->toDateString() : null);
        $tanggalJatuhTempo = $spkBahan->tanggal_jatuh_tempo ?: ($this->isTempoPayment($spkBahan->jenis_pembayaran) ? $spkBahan->tanggal_pembayaran : null);
        $data['tanggal_pemesanan'] = $tanggalPemesanan;
        $data['tanggal_jatuh_tempo'] = $tanggalJatuhTempo;
        $data['lama_pemesanan'] = $spkBahan->lama_pemesanan ?? $this->calculateLamaPemesanan($tanggalPemesanan);

        if ($this->isTempoPayment($spkBahan->jenis_pembayaran) && $tanggalPemesanan && $tanggalJatuhTempo) {
            $data['tempo_hari'] = Carbon::parse($tanggalPemesanan)
                ->startOfDay()
                ->diffInDays(Carbon::parse($tanggalJatuhTempo)->startOfDay(), false);
        }

        return $data;
    }

    private function calculateLamaPemesanan($tanggalPemesanan, $tanggalSelesai = null): ?int
    {
        if (!$tanggalPemesanan) {
            return null;
        }

        $startDate = Carbon::parse($tanggalPemesanan)->startOfDay();
        $endDate = $tanggalSelesai
            ? Carbon::parse($tanggalSelesai)->startOfDay()
            : Carbon::today();

        return (int) max(0, $startDate->diffInDays($endDate, false));
    }

    private function warnaOptionsForGroupAndPabrik(string $groupName, string $pabrikName): array
    {
        return Bahan::query()
            ->where('group_bahan', $groupName)
            ->whereRaw('LOWER(TRIM(pabrik_bahan)) = ?', [$this->normalizeKey($pabrikName)])
            ->whereNotNull('warna_bahan')
            ->where('warna_bahan', '<>', '')
            ->pluck('warna_bahan')
            ->mapWithKeys(fn ($warna) => [$this->normalizeKey($warna) => trim((string) $warna)])
            ->all();
    }

    private function resolvePabrikForSpk(array $validated): Pabrik
    {
        if (!empty($validated['pabrik_id'])) {
            return Pabrik::findOrFail($validated['pabrik_id']);
        }

        $pabrikName = trim((string) ($validated['pabrik_nama'] ?? ''));

        if ($pabrikName === '' || $pabrikName === '-') {
            abort(response()->json([
                'success' => false,
                'message' => 'Pabrik pada master bahan belum valid.',
                'errors' => ['pabrik_nama' => ['Pabrik pada master bahan belum valid.']],
            ], 422));
        }

        $existing = Pabrik::query()
            ->whereRaw('LOWER(TRIM(nama_pabrik)) = ?', [$this->normalizeKey($pabrikName)])
            ->first();

        return $existing ?: Pabrik::create([
            'nama_pabrik' => $pabrikName,
        ]);
    }

    private function bahanMatchesPabrik(Bahan $bahan, Pabrik $pabrik): bool
    {
        $bahanPabrik = trim((string) ($bahan->pabrik_bahan ?? ''));

        if ($bahanPabrik === '' || $bahanPabrik === '-') {
            return false;
        }

        return $this->normalizeKey($bahanPabrik) === $this->normalizeKey($pabrik->nama_pabrik);
    }

    private function isTempoPayment($value): bool
    {
        return $this->normalizeKey($value) === 'tempo';
    }

    private function normalizeKey($value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
