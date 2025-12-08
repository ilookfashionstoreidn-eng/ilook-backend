<?php

namespace App\Http\Controllers;

use App\Models\PembelianBahan;
use App\Models\PembelianBahanWarna;
use App\Models\PembelianBahanRol;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PembelianBahanController extends Controller
{

    public function index()
    {
        return response()->json(PembelianBahan::all());
    }

    public function show($id)
    {
        try {
            $pembelianBahan = PembelianBahan::with(['warna.rol', 'bahan', 'pabrik', 'gudang'])->findOrFail($id);
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
        try {
            $validated = $request->validate([
                'keterangan' => 'required|string',
                'gudang_id'  => 'required|exists:gudang,id',
                'pabrik_id'  => 'required|exists:pabrik,id',
                'tanggal_kirim' => 'required|date',
                'no_surat_jalan' => 'nullable|string|unique:pembelian_bahan,no_surat_jalan',
                'foto_surat_jalan' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5000',

                'sku' => 'nullable|string',
                'harga' => 'required|numeric|min:0',

                'bahan_id' => 'required|exists:bahan,id',
                'gramasi' => 'required|integer',
                'lebar_kain' => 'required|integer',

                'warna' => 'required|array',
                'warna.*.nama' => 'required|string',
                'warna.*.jumlah_rol' => 'required|integer',
                'warna.*.rol' => 'required|array',
                'warna.*.rol.*' => 'required|numeric',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        }

        $data = $request->all();

        if ($request->hasFile('foto_surat_jalan')) {
            $data['foto_surat_jalan'] = $request->file('foto_surat_jalan')->store('surat_jalan', 'public');
        }

        $pembelianBahan = PembelianBahan::create($data);

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
            'message' => 'Pembelian bahan berhasil disimpan',
            'data' => $pembelianBahan->load('warna.rol')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        try {
            $pembelianBahan = PembelianBahan::findOrFail($id);

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
                'gramasi' => 'required|integer',
                'lebar_kain' => 'required|integer',

                'warna' => 'required|array',
                'warna.*.nama' => 'required|string',
                'warna.*.jumlah_rol' => 'required|integer',
                'warna.*.rol' => 'required|array',
                'warna.*.rol.*' => 'required|numeric',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
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
}
