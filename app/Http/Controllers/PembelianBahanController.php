<?php

namespace App\Http\Controllers;

use App\Models\PembelianBahan;
use App\Models\PembelianBahanWarna;
use App\Models\PembelianBahanRol;
use Illuminate\Http\Request;
use App\Models\SpkBahan;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;

class PembelianBahanController extends Controller
{

  public function index(Request $request)
    {
        try {
            $query = PembelianBahan::with([
                    'spkBahan.warna',
                    'warna.rol',
                    'returns'
                ])
                ->orderBy('id', 'desc');

            // Search logic
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('keterangan', 'like', "%{$search}%")
                      ->orWhere('no_surat_jalan', 'like', "%{$search}%")
                      ->orWhereHas('spkBahan', function($q2) use ($search) {
                          $q2->where('id', 'like', "%{$search}%");
                      });
                });
            }

            // Callback for transformation
            $transformCallback = function ($item) {
                // total rol dikirim (dari rol aktual)
                $totalRolDikirim = $item->warna ? $item->warna->sum(function ($warna) {
                    return $warna->rol ? $warna->rol->count() : 0;
                }) : 0;

                // total rol di SPK
                $totalRolSpk = $item->spkBahan && $item->spkBahan->warna
                    ? $item->spkBahan->warna->sum('jumlah_rol')
                    : 0;

                // Hitung returns dengan aman
                $returns = $item->returns ?? collect([]);
                $totalReturn = $returns->count();
                $totalRefund = $returns->where('tipe_return', 'refund')->sum('total_refund') ?? 0;
                $totalReturnBarang = $returns->where('tipe_return', 'return_barang')->count();
                $totalRolReturned = $returns->where('status', '!=', 'rejected')->sum('jumlah_rol');

                return [
                    'id' => $item->id,
                    'tanggal_kirim' => $item->tanggal_kirim,
                    'no_surat_jalan' => $item->no_surat_jalan,
                    'keterangan' => $item->keterangan,
                    'harga' => $item->harga,
                    'sku' => $item->sku,

                    // ===== ID SAJA =====
                    'gudang_id' => $item->gudang_id,
                    'pabrik_id' => $item->pabrik_id,
                    'bahan_id'  => $item->bahan_id,

                    // ===== SPK =====
                    'spk' => $item->spkBahan ? [
                        'id' => $item->spkBahan->id,
                        'status' => $item->spkBahan->status,
                        'lama_pemesanan' => $item->spkBahan->lama_pemesanan,
                    ] : null,

                    // ===== WARNA & ROL =====
                    'warna' => $item->warna ? $item->warna->map(function ($warna) {
                        return [
                            'id' => $warna->id,
                            'spk_bahan_warna_id' => $warna->spk_bahan_warna_id,
                            'warna' => $warna->warna,
                            'jumlah_rol' => $warna->jumlah_rol,
                            'rol' => $warna->rol ? $warna->rol->map(function ($rol) {
                                return [
                                    'id' => $rol->id,
                                    'berat' => $rol->berat,
                                    'barcode' => $rol->barcode,
                                    'status' => $rol->status,
                                ];
                            }) : [],
                        ];
                    }) : [],

                    // ===== PROGRESS =====
                    'total_rol_dikirim' => $totalRolDikirim,
                    'total_rol_spk' => $totalRolSpk,
                    'progress' => $totalRolSpk > 0
                        ? round(($totalRolDikirim / $totalRolSpk) * 100, 2)
                        : 0,

                    // ===== RETURN/REFUND INFO =====
                    'returns' => [
                        'total_return' => $totalReturn,
                        'total_refund' => $totalRefund,
                        'total_return_barang' => $totalReturnBarang,
                        'total_rol_returned' => $totalRolReturned,
                    ],

                    'created_at' => $item->created_at,
                ];
            };

            // Pagination logic
            if ($request->has('per_page')) {
                $perPage = $request->input('per_page', 20);
                $paginator = $query->paginate($perPage);
                $paginator->getCollection()->transform($transformCallback);
                $data = $paginator;
            } else {
                // Backward compatibility: Return all data if no per_page param
                $data = $query->get()->map($transformCallback);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            Log::error('Error in PembelianBahanController@index: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data pembelian bahan',
                'error' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server'
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $pembelianBahan = PembelianBahan::with(['warna.rol', 'bahan', 'pabrik', 'gudang', 'spkBahan'])->findOrFail($id);
            return response()->json($pembelianBahan);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Data tidak ditemukan', 'error' => $e->getMessage()], 404);
        }
    }

    public function barcodesDebug($id)
    {
        try {
            $pembelianBahan = PembelianBahan::findOrFail($id);
            $barcodes = PembelianBahanRol::whereHas('warna', function ($q) use ($id) {
                $q->where('pembelian_bahan_id', $id);
            })->with('warna')->get();

            return response()->json([
                'pembelian_bahan_id' => $pembelianBahan->id,
                'total_barcodes' => $barcodes->count(),
                'samples' => $barcodes->take(5)->map(function ($r) {
                    return [
                        'barcode' => $r->barcode,
                        'berat' => $r->berat,
                        'warna' => optional($r->warna)->warna,
                    ];
                }),
            ]);
        } catch (\Throwable $e) {
            Log::error('Debug barcode pembelian bahan gagal: ' . $e->getMessage());
            return response()->json(['message' => 'Debug gagal', 'error' => $e->getMessage()], 500);
        }
}

public function store(Request $request)
{
    $validated = $request->validate([
        'spk_bahan_id'   => 'required|exists:spk_bahan,id',
        'keterangan'    => 'required|string',
        'gudang_id'     => 'required|exists:gudang,id',
        'pabrik_id'     => 'required|exists:pabrik,id',
        'tanggal_kirim' => 'required|date',
        'harga'         => 'required|numeric|min:0',
        'gramasi'       => 'required|numeric',
        'lebar_kain'    => 'required|numeric',

        'no_surat_jalan'   => 'nullable|string|unique:pembelian_bahan,no_surat_jalan',
        'foto_surat_jalan' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5000',

        // format
        // berat_rol: { spk_bahan_warna_id: [berat, berat, ...] }
        'berat_rol' => 'required|array',
    ]);

    DB::beginTransaction();

    try {
        /**
         * 1. Ambil SPK + warna
         */
        $spkBahan = SpkBahan::with('warna')->findOrFail($validated['spk_bahan_id']);
 
        /**
         * 2. Simpan header pembelian bahan
         */
        $data = $validated;
        $data['bahan_id'] = $spkBahan->bahan_id;
        $data['status_bayar'] = 'belum';

        if ($request->hasFile('foto_surat_jalan')) {
            $data['foto_surat_jalan'] = $request
                ->file('foto_surat_jalan')
                ->store('surat_jalan', 'public');
        }
       

        $pembelianBahan = PembelianBahan::create($data);

        /**
         * FLAG WAJIB ADA ROL
         */
        $adaRolDisimpan = false;

        /**
         * 3. Loop warna dari SPK
         */
        foreach ($spkBahan->warna as $spkWarna) {

            $beratRol = $validated['berat_rol'][$spkWarna->id] ?? [];

            // boleh skip warna yg belum dikirim
            if (count($beratRol) === 0) {
                continue;
            }

            /**
             * 4. Hitung sisa rol SPK
             */
            $totalTerkirim = PembelianBahanRol::whereHas('warna', function ($q) use ($spkWarna) {
                $q->where('spk_bahan_warna_id', $spkWarna->id);
            })->count();

            $sisaRol = $spkWarna->jumlah_rol - $totalTerkirim;

            if (count($beratRol) > $sisaRol) {
                throw new \Exception(
                    "Jumlah rol untuk warna {$spkWarna->warna} melebihi sisa SPK ({$sisaRol})"
                );
            }

            /**
             * 5. Simpan pembelian bahan warna
             *    jumlah_rol = YANG DIKIRIM
             */
            $pembelianWarna = PembelianBahanWarna::create([
                'pembelian_bahan_id' => $pembelianBahan->id,
                'spk_bahan_warna_id' => $spkWarna->id,
                'warna'              => $spkWarna->warna,
                'jumlah_rol'         => count($beratRol),
            ]);

            /**
             * 6. Simpan rol + berat
             */
            foreach ($beratRol as $berat) {
                PembelianBahanRol::create([
                    'pembelian_bahan_warna_id' => $pembelianWarna->id,
                    'berat'   => $berat,
                    'barcode' => 'BR-' . strtoupper(uniqid()),
                    'status'  => 'tersedia',
                ]);

                $adaRolDisimpan = true;
            }
        }

        /**
         * 7. WAJIB minimal 1 rol
         */
        if (!$adaRolDisimpan) {
            throw new \Exception(
                'Pembelian bahan gagal: warna dan rol belum diisi'
            );
        }

        /**
         * 8. Update status SPK jika semua rol sudah terkirim
         */
        $totalRolSpk = $spkBahan->warna->sum('jumlah_rol');

        $totalRolTerkirim = PembelianBahanRol::whereHas('warna', function ($q) use ($spkBahan) {
            $q->whereHas('pembelianBahan', function ($q2) use ($spkBahan) {
                $q2->where('spk_bahan_id', $spkBahan->id);
            });
        })->count();

        if ($totalRolTerkirim >= $totalRolSpk) {
            $spkBahan->update(['status' => 'selesai']);
        }

        /**
         * 9. Hitung selisih hari dari SPK dibuat sampai Pembelian Bahan dibuat
         *    Hanya update jika lama_pemesanan belum diisi (null)
         */
        if (is_null($spkBahan->lama_pemesanan)) {
            $tanggalSpk = $spkBahan->created_at->startOfDay();
            $tanggalPembelian = Carbon::parse($validated['tanggal_kirim'])->startOfDay();
            $selisihHari = $tanggalSpk->diffInDays($tanggalPembelian);
            
            $spkBahan->update(['lama_pemesanan' => $selisihHari]);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Pembelian bahan berhasil disimpan',
            'data' => $pembelianBahan->load([
                'warna.rol',
                'spkBahan',
                'bahan',
                'pabrik',
                'gudang'
            ])
        ], 201);

    } catch (\Throwable $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Gagal menyimpan pembelian bahan',
            'error' => $e->getMessage()
        ], 500);
    }
}


    public function update(Request $request, $id)
    {
        try {
            $pembelianBahan = PembelianBahan::findOrFail($id);

            // Log data yang diterima untuk debugging
            Log::info('Update pembelian bahan - Data diterima:', [
                'id' => $id,
                'request_data' => $request->all(),
                'warna' => $request->warna,
            ]);

            $validated = $request->validate([
                'keterangan' => 'required|string',
                'gudang_id'  => 'required|exists:gudang,id',
                'pabrik_id'  => 'required|exists:pabrik,id',
                'tanggal_kirim' => 'required|date',
                'no_surat_jalan' => 'nullable|string|unique:pembelian_bahan,no_surat_jalan,' . $id,
                'foto_surat_jalan' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5000',

                'sku' => 'nullable|string',
                'harga' => 'required|numeric|min:0',

                'bahan_id' => 'required|exists:bahan,id',
                'gramasi' => 'required|numeric',
                'lebar_kain' => 'required|numeric',

                'warna' => 'required|array|min:1',
                'warna.*.nama' => 'required|string|min:1',
                'warna.*.jumlah_rol' => 'required|integer|min:1',
                'warna.*.rol' => 'required|array|min:1',
                'warna.*.rol.*' => 'required|numeric|min:0',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Update pembelian bahan - Validasi gagal:', [
                'id' => $id,
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        }

        $data = $request->all();

        if ($request->hasFile('foto_surat_jalan')) {
            $data['foto_surat_jalan'] = $request->file('foto_surat_jalan')->store('surat_jalan', 'public');
        }

        $pembelianBahan->update($data);

        // Hapus warna dan rol lama
        PembelianBahanWarna::where('pembelian_bahan_id', $id)->delete();

        // Buat warna dan rol baru
        foreach ($request->warna as $warnaItem) {
            $warna = PembelianBahanWarna::create([
                'pembelian_bahan_id' => $pembelianBahan->id,
                'warna' => $warnaItem['nama'],
                'jumlah_rol' => $warnaItem['jumlah_rol'],
            ]);

            foreach ($warnaItem['rol'] as $berat) {
                PembelianBahanRol::create([
                    'pembelian_bahan_warna_id' => $warna->id,
                    'berat' => $berat,
                    'barcode' => 'BR-' . strtoupper(uniqid()),
                    'status' => 'tersedia'
                ]);
            }
        }

        return response()->json([
            'message' => 'Pembelian bahan berhasil diperbarui',
            'data' => $pembelianBahan->load('warna.rol')
        ], 200);
    }

    public function downloadBarcodes($id)
    {
        try {
            // Load pembelian bahan dengan semua relasi yang dibutuhkan
            $pembelianBahan = PembelianBahan::with(['pabrik', 'bahan', 'gudang'])->findOrFail($id);

            // Load barcodes dengan relasi warna dan pembelianBahan
            $barcodes = PembelianBahanRol::whereHas('warna', function ($q) use ($id) {
                $q->where('pembelian_bahan_id', $id);
            })->with(['warna'])->get();

            if ($barcodes->isEmpty()) {
                return response()->json(['message' => 'Barcode belum tersedia untuk pembelian ini'], 404);
            }

            $barcodes->transform(function ($rol) {
                $svg = QrCode::format('svg')->size(140)->generate($rol->barcode);
                $rol->setAttribute('qrSvg', $svg);
                return $rol;
            });
        } catch (\Throwable $e) {
            Log::error('DB error saat ambil data barcode pembelian bahan: ' . $e->getMessage());
            return response()->json(['message' => 'Koneksi database bermasalah atau data tidak valid'], 500);
        }

        try {
            // Log untuk debugging
            Log::info('Generating barcode PDF', [
                'pembelian_id' => $pembelianBahan->id,
                'template' => 'pdf.barcode_pembelian_bahan',
                'barcodes_count' => $barcodes->count(),
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);

            // Paper size disesuaikan untuk label yang lebih besar (100mm x 50mm)
            $pdf = Pdf::loadView('pdf.barcode_pembelian_bahan', [
                'barcodes' => $barcodes,
                'pembelianBahan' => $pembelianBahan,
            ])->setPaper([0, 0, 283.465, 141.732], 'portrait');


            return $pdf->download("barcode-bahan-NEW-{$pembelianBahan->id}.pdf");
        } catch (\Throwable $e) {
            Log::error('PDF barcode pembelian bahan gagal: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal membuat PDF barcode', 'error' => $e->getMessage()], 500);
        }
    }
    public function generateBarcodes($id)
    {
        try {
            $pembelianBahan = PembelianBahan::with('warna', 'warna.rol')->findOrFail($id);
            $created = 0;
            foreach ($pembelianBahan->warna as $warna) {
                $existing = $warna->rol->count();
                $target = (int) $warna->jumlah_rol;
                for ($i = $existing; $i < $target; $i++) {
                    PembelianBahanRol::create([
                        'pembelian_bahan_warna_id' => $warna->id,
                        'berat' => null,
                        'barcode' => 'BR-' . strtoupper(uniqid()),
                        'status' => 'tersedia',
                    ]);
                    $created++;
                }
            }
            return response()->json([
                'message' => 'Generate barcode selesai',
                'created' => $created,
                'pembelian_bahan_id' => $pembelianBahan->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Generate barcode pembelian bahan gagal: ' . $e->getMessage());
            return response()->json(['message' => 'Generate gagal', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get roll berdasarkan barcode untuk scan
     */
    public function getRollByBarcode($barcode)
    {
        try {
            $rol = PembelianBahanRol::with(['warna.pembelianBahan.bahan', 'warna.pembelianBahan.pabrik', 'warna.pembelianBahan.gudang'])
                ->where('barcode', $barcode)
                ->first();

            if (!$rol) {
                return response()->json([
                    'message' => 'Barcode tidak ditemukan',
                    'error' => 'not_found'
                ], 404);
            }

            return response()->json([
                'message' => 'Roll ditemukan',
                'data' => [
                    'id' => $rol->id,
                    'barcode' => $rol->barcode,
                    'berat' => $rol->berat,
                    'status' => $rol->status,
                    'warna' => $rol->warna->warna ?? null,
                    'pembelian_bahan_id' => $rol->warna->pembelian_bahan_id ?? null,
                    'bahan' => $rol->warna->pembelianBahan->bahan->nama_bahan ?? null,
                    'pabrik' => $rol->warna->pembelianBahan->pabrik->nama_pabrik ?? null,
                    'gudang' => $rol->warna->pembelianBahan->gudang->nama_gudang ?? null,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Get roll by barcode gagal: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengambil data roll', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update berat roll berdasarkan barcode (tanpa mengubah barcode)
     */
    public function updateBeratByBarcode(Request $request, $barcode)
    {
        try {
            $validated = $request->validate([
                'berat' => 'required|numeric|min:0',
            ]);

            $rol = PembelianBahanRol::where('barcode', $barcode)->first();

            if (!$rol) {
                return response()->json([
                    'message' => 'Barcode tidak ditemukan',
                    'error' => 'not_found'
                ], 404);
            }

            // Update berat tanpa mengubah barcode
            $rol->update([
                'berat' => $validated['berat']
            ]);

            return response()->json([
                'message' => 'Berat roll berhasil diperbarui',
                'data' => [
                    'id' => $rol->id,
                    'barcode' => $rol->barcode,
                    'berat' => $rol->berat,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Update berat by barcode gagal: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal memperbarui berat roll', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get list roll yang beratnya masih 0 atau null
     */
    public function getRollsWithZeroBerat()
    {
        try {
            $rolls = PembelianBahanRol::with(['warna.pembelianBahan.bahan', 'warna.pembelianBahan.pabrik', 'warna.pembelianBahan.gudang'])
                ->where(function ($query) {
                    $query->where('berat', 0)
                        ->orWhereNull('berat');
                })
                ->orderBy('id', 'desc')
                ->get();

            $formatted = $rolls->map(function ($rol) {
                return [
                    'id' => $rol->id,
                    'barcode' => $rol->barcode,
                    'berat' => $rol->berat,
                    'status' => $rol->status,
                    'warna' => $rol->warna->warna ?? null,
                    'pembelian_bahan_id' => $rol->warna->pembelian_bahan_id ?? null,
                    'bahan' => $rol->warna->pembelianBahan->bahan->nama_bahan ?? null,
                    'pabrik' => $rol->warna->pembelianBahan->pabrik->nama_pabrik ?? null,
                    'gudang' => $rol->warna->pembelianBahan->gudang->nama_gudang ?? null,
                    'keterangan' => $rol->warna->pembelianBahan->keterangan ?? null,
                ];
            });

            return response()->json([
                'message' => 'Data roll dengan berat 0 berhasil diambil',
                'data' => $formatted,
                'total' => $formatted->count()
            ]);
        } catch (\Throwable $e) {
            Log::error('Get rolls with zero berat gagal: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengambil data roll', 'error' => $e->getMessage()], 500);
        }
    }

}
