<?php

namespace App\Http\Controllers;

use App\Models\SpkBahan;
use App\Models\SpkBahanWarna;
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
            'sort_by' => 'nullable|in:id,tanggal_pembayaran,created_at,jumlah',
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
                $query->whereDate('tanggal_pembayaran', '>=', $validated['tanggal_mulai']);
            })
            ->when(!empty($validated['tanggal_selesai']), function ($query) use ($validated) {
                $query->whereDate('tanggal_pembayaran', '<=', $validated['tanggal_selesai']);
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
                            $bahanQuery->where('nama_bahan', 'like', "%{$search}%");
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

        $kpiRows = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_spk')
            ->selectRaw('COUNT(DISTINCT pabrik_id) as total_pabrik_aktif')
            ->selectRaw("SUM(CASE WHEN LOWER(jenis_pembayaran) = 'tempo' THEN 1 ELSE 0 END) as total_tempo")
            ->selectRaw('COALESCE(SUM(jumlah), 0) as total_rol')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
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
        $validated = $request->validate([
            'pabrik_id' => 'required|integer|exists:pabrik,id',
            'bahan_id' => 'required|integer|exists:bahan,id',
            'jenis_pembayaran' => 'required|string|max:40',
            'tanggal_pembayaran' => 'required|date',

            'warna' => 'required|array|min:1',
            'warna.*.warna' => 'required|string|max:50',
            'warna.*.jumlah_rol' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // 1. Simpan header SPK Bahan (jumlah sementara 0)
            $spkBahan = SpkBahan::create([
                'pabrik_id' => $validated['pabrik_id'],
                'bahan_id' => $validated['bahan_id'],
                'jumlah' => 0, // akan diupdate
                'jenis_pembayaran' => $validated['jenis_pembayaran'],
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
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
}
