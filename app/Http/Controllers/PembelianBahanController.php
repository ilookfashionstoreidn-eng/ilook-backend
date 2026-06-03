<?php

namespace App\Http\Controllers;

use App\Models\PembelianBahan;
use App\Models\PembelianBahanWarna;
use App\Models\PembelianBahanRol;
use App\Models\StokBahan;
use App\Models\StokBahanKeluar;
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
                    'spkBahan.bahan:id,nama_bahan,satuan',
                    'spkBahan.pabrik:id,nama_pabrik',
                    'bahan:id,nama_bahan,satuan,group_bahan,pabrik_bahan',
                    'pabrik:id,nama_pabrik',
                    'gudang:id,nama_gudang',
                    'warna.rol.stokBahan',
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
                    'bahan' => $item->bahan ? [
                        'id' => $item->bahan->id,
                        'nama_bahan' => $item->bahan->nama_bahan,
                        'satuan' => $item->bahan->satuan,
                        'group_bahan' => $item->bahan->group_bahan,
                        'pabrik_bahan' => $item->bahan->pabrik_bahan,
                    ] : null,
                    'pabrik' => $item->pabrik ? [
                        'id' => $item->pabrik->id,
                        'nama_pabrik' => $item->pabrik->nama_pabrik,
                    ] : null,
                    'gudang' => $item->gudang ? [
                        'id' => $item->gudang->id,
                        'nama_gudang' => $item->gudang->nama_gudang,
                    ] : null,

                    // ===== SPK =====
                    'spk' => $item->spkBahan ? [
                        'id' => $item->spkBahan->id,
                        'status' => $item->spkBahan->status,
                        'lama_pemesanan' => $item->spkBahan->lama_pemesanan,
                        'bahan_id' => $item->spkBahan->bahan_id,
                        'pabrik_id' => $item->spkBahan->pabrik_id,
                        'bahan' => $item->spkBahan->bahan ? [
                            'id' => $item->spkBahan->bahan->id,
                            'nama_bahan' => $item->spkBahan->bahan->nama_bahan,
                            'satuan' => $item->spkBahan->bahan->satuan,
                        ] : null,
                        'pabrik' => $item->spkBahan->pabrik ? [
                            'id' => $item->spkBahan->pabrik->id,
                            'nama_pabrik' => $item->spkBahan->pabrik->nama_pabrik,
                        ] : null,
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
                                    'sudah_scan_bahan_masuk' => (bool) $rol->stokBahan,
                                    'stok_bahan_id' => $rol->stokBahan->id ?? null,
                                    'scan_bahan_masuk_at' => $rol->stokBahan->scanned_at ?? null,
                                    'status_stok_bahan' => $rol->stokBahan->status ?? null,
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
            $pembelianBahan = PembelianBahan::with([
                'warna.rol.stokBahan',
                'bahan',
                'pabrik',
                'gudang',
                'spkBahan.bahan:id,nama_bahan,satuan',
                'spkBahan.pabrik:id,nama_pabrik',
            ])->findOrFail($id);
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
        'gramasi'       => 'required|string|max:100',
        'lebar_kain'    => 'required|numeric',

        'no_surat_jalan'   => 'nullable|string|unique:pembelian_bahan,no_surat_jalan',
        'foto_surat_jalan' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5000',

        // format
        // berat_rol: { spk_bahan_warna_id: [berat, berat, ...] }
        'berat_rol' => 'required|array',
        'berat_rol.*' => 'required|array',
        'berat_rol.*.*' => 'nullable|numeric|min:0',
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
                    'berat'   => $berat ?? 0,
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
         * 9. Hitung selisih hari dari tanggal pemesanan SPK sampai bahan dikirim
         *    Hanya update jika lama_pemesanan belum diisi (null)
         */
        if (is_null($spkBahan->lama_pemesanan)) {
            $tanggalSpk = Carbon::parse($spkBahan->tanggal_pemesanan ?: $spkBahan->created_at)->startOfDay();
            $tanggalPembelian = Carbon::parse($validated['tanggal_kirim'])->startOfDay();
            $selisihHari = (int) max(0, $tanggalSpk->diffInDays($tanggalPembelian, false));
            
            $spkBahan->update(['lama_pemesanan' => $selisihHari]);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Pembelian bahan berhasil disimpan',
            'data' => $pembelianBahan->load([
                'warna.rol',
                'spkBahan',
                'spkBahan.bahan:id,nama_bahan,satuan',
                'spkBahan.pabrik:id,nama_pabrik',
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


    public function storeOpname(Request $request)
    {
        $validated = $request->validate([
            'keterangan'    => 'required|string',
            'gudang_id'     => 'required|exists:gudang,id',
            'pabrik_id'     => 'required|exists:pabrik,id',
            'bahan_id'      => 'required|exists:bahan,id',
            'tanggal_kirim' => 'required|date',
            'harga'         => 'required|numeric|min:0',
            'gramasi'       => 'required|string|max:100',
            'lebar_kain'    => 'required|numeric',
            
            // format: warna => array of berats
            'warna'         => 'required|array',
            'warna.*'       => 'required|array', // array of berat
        ]);

        DB::beginTransaction();

        try {
            /**
             * 1. Simpan header pembelian bahan (tanpa spk_bahan_id)
             */
            $data = [
                'keterangan'    => $validated['keterangan'],
                'gudang_id'     => $validated['gudang_id'],
                'pabrik_id'     => $validated['pabrik_id'],
                'bahan_id'      => $validated['bahan_id'],
                'tanggal_kirim' => $validated['tanggal_kirim'],
                'harga'         => $validated['harga'],
                'gramasi'       => $validated['gramasi'],
                'lebar_kain'    => $validated['lebar_kain'],
                'status_bayar'  => 'sudah',
                'no_surat_jalan'=> 'OPNAME-' . strtoupper(uniqid()),
            ];

            $pembelianBahan = PembelianBahan::create($data);

            $adaRolDisimpan = false;

            /**
             * 2. Loop warna
             */
            foreach ($validated['warna'] as $namaWarna => $beratRol) {
                if (count($beratRol) === 0) {
                    continue;
                }

                $pembelianWarna = PembelianBahanWarna::create([
                    'pembelian_bahan_id' => $pembelianBahan->id,
                    'spk_bahan_warna_id' => null,
                    'warna'              => $namaWarna,
                    'jumlah_rol'         => count($beratRol),
                ]);

                foreach ($beratRol as $berat) {
                    $barcode = 'BR-' . strtoupper(uniqid());
                    $rol = PembelianBahanRol::create([
                        'pembelian_bahan_warna_id' => $pembelianWarna->id,
                        'berat'   => $berat ?? 0,
                        'barcode' => $barcode,
                        'status'  => 'tersedia',
                    ]);
                    
                    // Langsung masukkan ke StokBahan agar otomatis masuk ke stok aktif
                    StokBahan::create([
                        'pembelian_bahan_id'       => $pembelianBahan->id,
                        'pembelian_bahan_warna_id' => $pembelianWarna->id,
                        'pembelian_bahan_rol_id'   => $rol->id,
                        'gudang_id'                => $validated['gudang_id'],
                        'pabrik_id'                => $validated['pabrik_id'],
                        'barcode'                  => $barcode,
                        'berat'                    => $berat ?? 0,
                        'scanned_at'               => Carbon::now(),
                        'status'                   => 'tersedia',
                    ]);

                    $adaRolDisimpan = true;
                }
            }

            if (!$adaRolDisimpan) {
                throw new \Exception('Stok Opname gagal: warna dan rol belum diisi');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stok opname berhasil disimpan dan barcode telah di-generate.',
                'data' => collect(['id' => $pembelianBahan->id])
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Stok Opname error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan stok opname',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroyOpname($id)
    {
        DB::beginTransaction();
        try {
            $pembelianBahan = PembelianBahan::findOrFail($id);
            
            \App\Models\PendapatanPabrikDetail::where('pembelian_bahan_id', $id)->delete();
            
            StokBahan::where('pembelian_bahan_id', $id)->delete();
            
            $warnaIds = PembelianBahanWarna::where('pembelian_bahan_id', $id)->pluck('id');
            PembelianBahanRol::whereIn('pembelian_bahan_warna_id', $warnaIds)->delete();
            
            PembelianBahanWarna::where('pembelian_bahan_id', $id)->delete();
            
            $pembelianBahan->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data stok opname berhasil dihapus.'
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Delete Stok Opname error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus stok opname',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroyRol($id)
    {
        try {
            $rol = PembelianBahanRol::findOrFail($id);
            // Hapus stok bahan yang terkait jika ada
            StokBahan::where('barcode', $rol->barcode)->delete();
            
            $warnaId = $rol->pembelian_bahan_warna_id;
            $rol->delete();
            
            // Cek apakah ini rol terakhir di warna tersebut
            $sisaRol = PembelianBahanRol::where('pembelian_bahan_warna_id', $warnaId)->count();
            if ($sisaRol == 0) {
                // update jumlah_rol di tabel warna
                PembelianBahanWarna::where('id', $warnaId)->update(['jumlah_rol' => 0]);
            } else {
                PembelianBahanWarna::where('id', $warnaId)->update(['jumlah_rol' => $sisaRol]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Rol berhasil dihapus'
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus Rol',
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
                'gramasi' => 'required|string|max:100',
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
            'data' => $pembelianBahan->load([
                'warna.rol',
                'bahan:id,nama_bahan,satuan,group_bahan,pabrik_bahan',
                'pabrik:id,nama_pabrik',
                'gudang:id,nama_gudang',
                'spkBahan.bahan:id,nama_bahan,satuan',
                'spkBahan.pabrik:id,nama_pabrik',
            ])
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

            $result = DB::transaction(function () use ($barcode, $validated) {
                $rol = PembelianBahanRol::where('barcode', $barcode)
                    ->lockForUpdate()
                    ->first();

                if (!$rol) {
                    return null;
                }

                $berat = $validated['berat'];

                $rol->update([
                    'berat' => $berat
                ]);

                $stokBahanIds = StokBahan::where('pembelian_bahan_rol_id', $rol->id)
                    ->orWhere('barcode', $barcode)
                    ->pluck('id');

                $stokBahanUpdated = $stokBahanIds->isNotEmpty()
                    ? StokBahan::whereIn('id', $stokBahanIds)->update(['berat' => $berat])
                    : 0;

                $stokBahanKeluarQuery = StokBahanKeluar::where('barcode', $barcode);

                if ($stokBahanIds->isNotEmpty()) {
                    $stokBahanKeluarQuery->orWhereIn('stok_bahan_id', $stokBahanIds);
                }

                $stokBahanKeluarUpdated = $stokBahanKeluarQuery->update(['berat' => $berat]);

                return [
                    'rol' => $rol->fresh(),
                    'updated' => [
                        'pembelian_bahan_rol' => 1,
                        'stok_bahan' => $stokBahanUpdated,
                        'stok_bahan_keluar' => $stokBahanKeluarUpdated,
                    ],
                ];
            });

            if (!$result) {
                return response()->json([
                    'message' => 'Barcode tidak ditemukan',
                    'error' => 'not_found'
                ], 404);
            }

            return response()->json([
                'message' => 'Berat roll berhasil diperbarui dan disinkronkan ke stok bahan',
                'data' => [
                    'id' => $result['rol']->id,
                    'barcode' => $result['rol']->barcode,
                    'berat' => $result['rol']->berat,
                ],
                'updated' => $result['updated'],
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
