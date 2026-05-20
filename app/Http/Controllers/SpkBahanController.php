<?php

namespace App\Http\Controllers;

use App\Models\Bahan;
use App\Models\Pabrik;
use App\Models\SpkBahan;
use App\Models\SpkBahanWarna;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                'pembelianBahan:id,spk_bahan_id,tanggal_kirim',
                'pembelianBahan.warna:id,pembelian_bahan_id,spk_bahan_warna_id,warna,jumlah_rol',
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

    // ADDED: Generate PDF untuk SPK pemesanan bahan yang dipilih dari modal print.
    public function printPdf(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'spk_ids' => 'required|array|min:1',
            'spk_ids.*' => 'required|integer|distinct|exists:spk_bahan,id',
        ])->validate();

        $spkIds = collect($validated['spk_ids'])
            ->map(fn ($id) => (int) $id)
            ->values();

        $spkBahans = SpkBahan::query()
            ->with([
                'pabrik',
                'bahan.bahanImage',
                'warna',
                // ADDED: Dipakai untuk kolom Pesanan Dikirim dan Sisa Dipesan di PDF.
                'pembelianBahan.warna',
            ])
            ->whereIn('id', $spkIds)
            ->get()
            ->sortBy(fn (SpkBahan $spkBahan) => $spkIds->search((int) $spkBahan->id))
            ->values();

        $spkBahans->each(function (SpkBahan $spkBahan) {
            $imagePath = $spkBahan->bahan?->bahanImage?->image_path;
            $imageBase64 = null;

            if ($imagePath) {
                $normalizedPath = ltrim(str_replace(['public/', 'storage/'], '', $imagePath), '/\\');
                $candidatePaths = array_filter([
                    Storage::disk('public')->exists($normalizedPath) ? Storage::disk('public')->path($normalizedPath) : null,
                    storage_path('app/public/' . $normalizedPath),
                    public_path('storage/' . $normalizedPath),
                ]);

                foreach (array_unique($candidatePaths) as $candidatePath) {
                    if (is_file($candidatePath)) {
                        $imageBase64 = 'data:' . mime_content_type($candidatePath) . ';base64,' . base64_encode(file_get_contents($candidatePath));
                        break;
                    }
                }
            }

            // ADDED: Ringkasan order/delivery per warna untuk layout PDF.
            $pengirimanByWarnaId = [];
            $pengirimanByWarnaName = [];

            foreach ($spkBahan->pembelianBahan as $pembelian) {
                foreach ($pembelian->warna as $warnaDikirim) {
                    $jumlahDikirim = (int) ($warnaDikirim->jumlah_rol ?? 0);
                    $warnaId = $warnaDikirim->spk_bahan_warna_id;
                    $warnaKey = $this->normalizeKey($warnaDikirim->warna ?? '');

                    if ($warnaId) {
                        $pengirimanByWarnaId[$warnaId] = ($pengirimanByWarnaId[$warnaId] ?? 0) + $jumlahDikirim;
                    }

                    if ($warnaKey !== '') {
                        $pengirimanByWarnaName[$warnaKey] = ($pengirimanByWarnaName[$warnaKey] ?? 0) + $jumlahDikirim;
                    }
                }
            }

            $warnaDetailRows = $spkBahan->warna
                ->map(function ($warna) use ($pengirimanByWarnaId, $pengirimanByWarnaName) {
                    $stokDipesan = (int) ($warna->jumlah_rol ?? 0);
                    $warnaKey = $this->normalizeKey($warna->warna ?? '');
                    $pesananDikirim = (int) ($pengirimanByWarnaId[$warna->id] ?? ($warnaKey !== '' ? ($pengirimanByWarnaName[$warnaKey] ?? 0) : 0));

                    return [
                        'warna' => $warna->warna ?: '-',
                        'stok_dipesan' => $stokDipesan,
                        'pesanan_dikirim' => $pesananDikirim,
                        'sisa_dipesan' => max(0, $stokDipesan - $pesananDikirim),
                    ];
                })
                ->values();

            if ($warnaDetailRows->isEmpty()) {
                $warnaDetailRows = collect([[
                    'warna' => '-',
                    'stok_dipesan' => (int) ($spkBahan->jumlah ?? 0),
                    'pesanan_dikirim' => 0,
                    'sisa_dipesan' => (int) ($spkBahan->jumlah ?? 0),
                ]]);
            }

            $stokDipesan = (int) $warnaDetailRows->sum('stok_dipesan');
            $pesananDikirim = (int) $warnaDetailRows->sum('pesanan_dikirim');
            $sisaDipesan = (int) $warnaDetailRows->sum('sisa_dipesan');
            $tanggalKirimPertama = $spkBahan->pembelianBahan
                ->filter(fn ($pembelian) => !empty($pembelian->tanggal_kirim))
                ->min('tanggal_kirim');

            $spkBahan->bahan_image_base64 = $imageBase64;
            $spkBahan->pdf_warna_detail = $warnaDetailRows->all();
            $spkBahan->pdf_subtotal = [
                'stok_dipesan' => $stokDipesan,
                'pesanan_dikirim' => $pesananDikirim,
                'sisa_dipesan' => $sisaDipesan,
            ];
            $spkBahan->pdf_stok_dipesan = $stokDipesan;
            $spkBahan->pdf_pesanan_dikirim = $pesananDikirim;
            $spkBahan->pdf_sisa_dipesan = $sisaDipesan;
            $spkBahan->pdf_lama_pesan = $this->calculateLamaPemesanan(
                $spkBahan->tanggal_pemesanan ?: ($spkBahan->created_at ? Carbon::parse($spkBahan->created_at)->toDateString() : null),
                $spkBahan->estimasi_pengiriman ?: $tanggalKirimPertama
            );
        });

        $printedAt = Carbon::now('Asia/Jakarta');
        $fileName = 'SPK-Bahan-' . $printedAt->format('Y-m-d') . '.pdf';

        $pdf = Pdf::loadView('pdf.spk-bahan', [
            'spkBahans' => $spkBahans,
            'printedAt' => $printedAt,
            'companyName' => 'ILOOKSHOP.STORE',
        ])->setPaper('a4', 'landscape');

        return $pdf->download($fileName);
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

    public function updateEstimasiPengiriman(Request $request, $id)
    {
        $validated = Validator::make($request->all(), [
            'estimasi_pengiriman' => 'nullable|date',
            'spk_bahan_warna_id' => 'nullable|integer|exists:spk_bahan_warna,id',
        ])->validate();

        $spkBahan = SpkBahan::with([
            'pabrik',
            'bahan',
            'warna',
            'pembelianBahan:id,spk_bahan_id,tanggal_kirim',
            'pembelianBahan.warna:id,pembelian_bahan_id,spk_bahan_warna_id,warna,jumlah_rol',
        ])->findOrFail($id);

        $tanggalPemesanan = $spkBahan->tanggal_pemesanan ?: ($spkBahan->created_at ? Carbon::parse($spkBahan->created_at)->toDateString() : null);
        $estimasiPengiriman = !empty($validated['estimasi_pengiriman'])
            ? Carbon::parse($validated['estimasi_pengiriman'])->toDateString()
            : null;

        if ($tanggalPemesanan && $estimasiPengiriman && Carbon::parse($estimasiPengiriman)->lt(Carbon::parse($tanggalPemesanan))) {
            return response()->json([
                'success' => false,
                'message' => 'Estimasi pengiriman tidak boleh lebih awal dari tanggal pemesanan.',
                'errors' => [
                    'estimasi_pengiriman' => ['Estimasi pengiriman tidak boleh lebih awal dari tanggal pemesanan.'],
                ],
            ], 422);
        }

        if (!empty($validated['spk_bahan_warna_id'])) {
            $warna = $spkBahan->warna->firstWhere('id', (int) $validated['spk_bahan_warna_id']);

            if (!$warna) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warna tidak ditemukan pada SPK Bahan ini.',
                    'errors' => [
                        'spk_bahan_warna_id' => ['Warna tidak ditemukan pada SPK Bahan ini.'],
                    ],
                ], 422);
            }

            $warna->update([
                'estimasi_pengiriman' => $estimasiPengiriman,
            ]);
        } else {
            $spkBahan->update([
                'estimasi_pengiriman' => $estimasiPengiriman,
            ]);
        }

        $spkBahan->refresh()->load([
            'pabrik',
            'bahan',
            'warna',
            'pembelianBahan:id,spk_bahan_id,tanggal_kirim',
            'pembelianBahan.warna:id,pembelian_bahan_id,spk_bahan_warna_id,warna,jumlah_rol',
        ]);

        return response()->json([
            'success' => true,
            'message' => $estimasiPengiriman
                ? 'Estimasi pengiriman berhasil disimpan.'
                : 'Estimasi pengiriman berhasil dihapus.',
            'data' => $this->serializeSpkBahan($spkBahan),
        ]);
    }

    private function serializeSpkBahan(SpkBahan $spkBahan): array
    {
        $data = $spkBahan->toArray();
        unset($data['pembelian_bahan']);

        $data['group_bahan'] = $spkBahan->bahan?->group_bahan;
        $tanggalPemesanan = $spkBahan->tanggal_pemesanan ?: ($spkBahan->created_at ? Carbon::parse($spkBahan->created_at)->toDateString() : null);
        $tanggalJatuhTempo = $spkBahan->tanggal_jatuh_tempo ?: ($this->isTempoPayment($spkBahan->jenis_pembayaran) ? $spkBahan->tanggal_pembayaran : null);
        $estimasiPengiriman = $spkBahan->estimasi_pengiriman ? Carbon::parse($spkBahan->estimasi_pengiriman)->toDateString() : null;
        $tanggalKirimPertama = $spkBahan->relationLoaded('pembelianBahan')
            ? $spkBahan->pembelianBahan
                ->filter(fn ($pembelian) => !empty($pembelian->tanggal_kirim))
                ->min('tanggal_kirim')
            : null;
        $pengirimanSummary = $this->buildPengirimanSummary($spkBahan);

        $data['tanggal_pemesanan'] = $tanggalPemesanan;
        $data['tanggal_jatuh_tempo'] = $tanggalJatuhTempo;
        $data['estimasi_pengiriman'] = $estimasiPengiriman;
        $data['lama_pemesanan'] = $this->calculateLamaPemesanan(
            $tanggalPemesanan,
            $estimasiPengiriman ?: $tanggalKirimPertama
        );
        $data['warna'] = $pengirimanSummary['warna_detail'];
        $data['stok_dipesan'] = $pengirimanSummary['stok_dipesan'];
        $data['pesanan_dikirim'] = $pengirimanSummary['pesanan_dikirim'];
        $data['sisa_dipesan'] = $pengirimanSummary['sisa_dipesan'];
        $data['pdf_subtotal'] = $pengirimanSummary['pdf_subtotal'];
        $data['pdf_stok_dipesan'] = $pengirimanSummary['stok_dipesan'];
        $data['pdf_pesanan_dikirim'] = $pengirimanSummary['pesanan_dikirim'];
        $data['pdf_sisa_dipesan'] = $pengirimanSummary['sisa_dipesan'];

        if ($this->isTempoPayment($spkBahan->jenis_pembayaran) && $tanggalPemesanan && $tanggalJatuhTempo) {
            $data['tempo_hari'] = Carbon::parse($tanggalPemesanan)
                ->startOfDay()
                ->diffInDays(Carbon::parse($tanggalJatuhTempo)->startOfDay(), false);
        }

        return $data;
    }

    private function buildPengirimanSummary(SpkBahan $spkBahan): array
    {
        $pengirimanByWarnaId = [];
        $pengirimanByWarnaName = [];

        $pembelianRows = $spkBahan->relationLoaded('pembelianBahan')
            ? $spkBahan->pembelianBahan
            : collect();

        foreach ($pembelianRows as $pembelian) {
            $warnaRows = $pembelian->relationLoaded('warna') ? $pembelian->warna : collect();

            foreach ($warnaRows as $warnaDikirim) {
                $jumlahDikirim = (int) ($warnaDikirim->jumlah_rol ?? 0);
                $warnaId = $warnaDikirim->spk_bahan_warna_id;
                $warnaKey = $this->normalizeKey($warnaDikirim->warna ?? '');

                if ($warnaId) {
                    $pengirimanByWarnaId[$warnaId] = ($pengirimanByWarnaId[$warnaId] ?? 0) + $jumlahDikirim;
                }

                if ($warnaKey !== '') {
                    $pengirimanByWarnaName[$warnaKey] = ($pengirimanByWarnaName[$warnaKey] ?? 0) + $jumlahDikirim;
                }
            }
        }

        $warnaRows = $spkBahan->relationLoaded('warna') ? $spkBahan->warna : collect();
        $warnaDetailRows = $warnaRows
            ->map(function ($warna) use ($spkBahan, $pengirimanByWarnaId, $pengirimanByWarnaName) {
                $stokDipesan = (int) ($warna->jumlah_rol ?? 0);
                $warnaKey = $this->normalizeKey($warna->warna ?? '');
                $pesananDikirim = (int) ($pengirimanByWarnaId[$warna->id] ?? ($warnaKey !== '' ? ($pengirimanByWarnaName[$warnaKey] ?? 0) : 0));
                $tanggalPemesanan = $spkBahan->tanggal_pemesanan ?: ($spkBahan->created_at ? Carbon::parse($spkBahan->created_at)->toDateString() : null);
                $estimasiPengiriman = $warna->estimasi_pengiriman
                    ? Carbon::parse($warna->estimasi_pengiriman)->toDateString()
                    : null;

                return [
                    'id' => $warna->id,
                    'spk_bahan_id' => $warna->spk_bahan_id,
                    'warna' => $warna->warna ?: '-',
                    'jumlah_rol' => $stokDipesan,
                    'estimasi_pengiriman' => $estimasiPengiriman,
                    'lama_pemesanan' => $this->calculateLamaPemesanan($tanggalPemesanan, $estimasiPengiriman),
                    'stok_dipesan' => $stokDipesan,
                    'pesanan_dikirim' => $pesananDikirim,
                    'sisa_dipesan' => max(0, $stokDipesan - $pesananDikirim),
                ];
            })
            ->values();

        if ($warnaDetailRows->isEmpty()) {
            $stokDipesan = (int) ($spkBahan->jumlah ?? 0);
            $warnaDetailRows = collect([[
                'id' => null,
                'spk_bahan_id' => $spkBahan->id,
                'warna' => '-',
                'jumlah_rol' => $stokDipesan,
                'stok_dipesan' => $stokDipesan,
                'pesanan_dikirim' => 0,
                'sisa_dipesan' => $stokDipesan,
            ]]);
        }

        $stokDipesan = (int) $warnaDetailRows->sum('stok_dipesan');
        $pesananDikirim = (int) $warnaDetailRows->sum('pesanan_dikirim');
        $sisaDipesan = (int) $warnaDetailRows->sum('sisa_dipesan');

        return [
            'warna_detail' => $warnaDetailRows->all(),
            'stok_dipesan' => $stokDipesan,
            'pesanan_dikirim' => $pesananDikirim,
            'sisa_dipesan' => $sisaDipesan,
            'pdf_subtotal' => [
                'stok_dipesan' => $stokDipesan,
                'pesanan_dikirim' => $pesananDikirim,
                'sisa_dipesan' => $sisaDipesan,
            ],
        ];
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
