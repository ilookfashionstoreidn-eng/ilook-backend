<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkCmt;
use App\Models\Penjahit;
use App\Models\Warna;
use App\Models\LogDeadline;
use App\Models\LogStatus;
use App\Models\Pengiriman;
use App\Models\Produk;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use PDF;
use Illuminate\Support\Facades\DB;



class SpkCmtController extends Controller
{
    // Menampilkan semua SPK
    public function index(Request $request)
{
    $user = auth()->user();
    
    // Ambil semua filter dari query parameter
    $statusFilter = $request->query('status');
    $idPenjahit = $request->query('id_penjahit');
    $idProduk = $request->query('id_produk');
    $kategoriProduk = $request->query('kategori_produk');
    $sortBy = $request->query('sortBy', 'created_at'); 
    $sortOrder = $request->query('sortOrder', 'desc');
    $allData = $request->query('allData');
    $sortColumn = $sortBy === 'sisa_hari' ? 'deadline' : $sortBy;

    // Query awal dengan relasi
    $query = SpkCmt::with(['warna', 'pengiriman', 'produk:id,nama_produk,kategori_produk,gambar_produk']);

    // Filter khusus untuk penjahit
    if ($user->hasRole('penjahit')) {
        $query->where('id_penjahit', $user->id_penjahit);
    }

    $query->when($statusFilter, fn($q) => $q->where('status', $statusFilter))
          ->when($idPenjahit, fn($q) => $q->where('id_penjahit', $idPenjahit))
          ->when($idProduk, fn($q) => $q->where('id_produk', (int) $idProduk)) // Pastikan tipe data sesuai
          ->when($kategoriProduk, fn($q) => $q->whereHas('produk', fn($q) => $q->where('kategori_produk', $kategoriProduk))) // Filter kategori
          ->orderBy($sortColumn, $sortOrder);

          


    // Ambil data berdasarkan parameter allData
    $spk = $allData == 'true' ? $query->get() : $query->paginate(10);
    $kategoriCount = SpkCmt::join('produk', 'spk_cmt.id_produk', '=', 'produk.id')
    ->select(
        'produk.kategori_produk',
       DB::raw('COUNT(DISTINCT spk_cmt.id_produk) as total_produk')

    )
    ->when($idPenjahit, fn($q) => $q->where('spk_cmt.id_penjahit', $idPenjahit)) 
    ->groupBy('produk.kategori_produk')
    ->get();

    
// Ambil daftar produk yang kategori_produk-nya "urgent"
$urgentProducts = SpkCmt::join('produk', 'spk_cmt.id_produk', '=', 'produk.id')
    ->select('produk.nama_produk', 'produk.kategori_produk')
    ->when($idPenjahit, fn($q) => $q->where('spk_cmt.id_penjahit', $idPenjahit))
    ->where('produk.kategori_produk', 'urgent') // Filter hanya kategori "urgent"
    ->distinct()
    ->get();


    // Mapping data sebelum dikembalikan
    $spk->transform(function ($item) {
        $item->sisa_hari = $item->sisa_hari;
        $item->status_with_color = $item->status_with_color;
        $item->total_barang_dikirim = $item->pengiriman->sum('total_barang_dikirim');
        $item->nama_produk = $item->produk->nama_produk ?? null;
        $item->kategori_produk = $item->produk->kategori_produk ?? null;
        $item->gambar_produk = $item->produk->gambar_produk ?? null;
        $item->nama_penjahit = $item->penjahit->nama_penjahit ?? null;
    
        // ✅ Tambahkan ini:
        $item->pengiriman->transform(function ($pengiriman) {
            $pengiriman->sisa_barang_per_warna = $pengiriman->warna
                ->groupBy('warna')
                ->map(function ($items) {
                    return $items->sum('sisa_barang_per_warna');
                });
            return $pengiriman;
        });
    
        return $item;
    });
    

   return response()->json([
        'spk' => $spk,
        'kategori_count' => $kategoriCount, // Tambahkan data jumlah kategori produk berdasarkan CMT
        'urgent_products' => $urgentProducts,
    ]);
}

   
    // Menampilkan form untuk membuat SPK baru (untuk React, ini bisa digantikan dengan form di frontend)
    public function create()
    {
        $penjahits = Penjahit::all();
        return response()->json($penjahits); // Mengembalikan data penjahit untuk digunakan di frontend
    }

    // Menyimpan SPK baru
    public function store(Request $request)
        {if (is_string($request->input('warna'))) {
            $request->merge([
                'warna' => json_decode($request->input('warna'), true),
            ]);
        }

            $validated = $request->validate([
            'id_produk' => 'required|exists:produk,id',
                'deadline' => 'required|date',
                'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
                'keterangan' => 'nullable|string',
                'tgl_spk' => 'required|date',
                'status' => 'required|string|in:Pending,In Progress,Completed',
                'nomor_seri' => 'nullable|string',
                'tanggal_ambil' => 'nullable|date',
                'catatan' => 'nullable|string',
                'markeran' => 'nullable|string',
                'aksesoris' => 'nullable|string',
                'handtag' => 'nullable|string',
                'merek' => 'nullable|string',
                'harga_per_barang' => 'required|numeric',
                'harga_per_jasa' => 'required|numeric',
                'jenis_harga_jasa' => 'required|in:per_barang,per_lusin', 
                'warna' => 'required|array',
                'warna.*.nama_warna' => 'required|string|max:50',
                'warna.*.qty' => 'required|integer|min:1',
            ]);
        
            $harga_jasa_awal = $request->harga_per_jasa;

            $harga_per_jasa = $request->jenis_harga_jasa === 'per_lusin'
            ? $harga_jasa_awal / 12
            : $harga_jasa_awal;

            // Hitung total jumlah produk
            $jumlahProduk = collect($validated['warna'])->sum('qty');
            $validated['jumlah_produk'] = $jumlahProduk;

            // Hitung total harga
            $totalHarga = $validated['harga_per_barang'] * $jumlahProduk;
            $validated['total_harga'] = $totalHarga;

        
        
            // Membuat SPK baru
            $spk = SpkCmt::create(array_merge($validated, [
                'jumlah_produk' => $jumlahProduk,
                'total_harga' => $totalHarga,
                'harga_per_jasa' => $harga_per_jasa,
                'harga_jasa_awal' => $harga_jasa_awal, // Simpan harga awal
            ]));

            // Simpan warna ke dalam tabel warna
            foreach ($validated['warna'] as $warna) {
                Warna::create([
                    'id_spk' => $spk->id_spk,
                    'nama_warna' => $warna['nama_warna'],
                    'qty' => $warna['qty'],
                ]);
            }

            return response()->json([
                'message' => 'SPK dan Warna berhasil dibuat!',
                'data' => [
                    'spk' => $spk,
                    'nama_produk' => $spk->produk->nama_produk ?? null // Ambil nama produk jika ada
                ]
            ], 201);
        }

    // Menampilkan SPK berdasarkan ID
    public function show($id)
    {
        $spk = SpkCmt::findOrFail($id);
        return response()->json($spk); // Mengembalikan data SPK berdasarkan ID
    }

    // Menampilkan form untuk mengedit SPK (untuk React, ini bisa digantikan dengan form di frontend)
    public function edit($id)
    {
        $spk = SpkCmt::findOrFail($id);
        $penjahits = Penjahit::all();
        return response()->json(['spk' => $spk, 'penjahits' => $penjahits]); // Mengembalikan data SPK dan penjahit
    }

    public function update(Request $request, $id)
    {
        // Parsing warna jika dikirim sebagai string JSON dari frontend
        if (is_string($request->input('warna'))) {
            $request->merge([
                'warna' => json_decode($request->input('warna'), true),
            ]);
        }
    
        $validated = $request->validate([
            'id_produk' => 'required|exists:produk,id',
            'deadline' => 'required|date',
            'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
            'keterangan' => 'nullable|string',
            'tgl_spk' => 'required|date',
            'status' => 'required|string|in:Pending,In Progress,Completed',
            'tanggal_ambil' => 'nullable|date',
            'catatan' => 'nullable|string',
            'markeran' => 'nullable|string',
            'nomor_seri' => 'nullable|string',
            'aksesoris' => 'nullable|string',
            'handtag' => 'nullable|string',
            'merek' => 'nullable|string',
            'harga_per_barang' => 'required|numeric',
            'harga_per_jasa' => 'required|numeric',
            'jenis_harga_jasa' => 'required|in:per_barang,per_lusin',
            'warna' => 'required|array',
            'warna.*.id_warna' => 'nullable|exists:warna,id_warna',
            'warna.*.nama_warna' => 'required|string|max:50',
            'warna.*.qty' => 'required|integer|min:1',
        ]);
        
    
        $spk = SpkCmt::findOrFail($id);
    
        $harga_jasa_awal = $validated['harga_per_jasa'];
        $harga_per_jasa = $validated['jenis_harga_jasa'] === 'per_lusin'
            ? $harga_jasa_awal / 12
            : $harga_jasa_awal;
    
        $jumlahProduk = collect($validated['warna'])->sum('qty');
        $totalHarga = $validated['harga_per_barang'] * $jumlahProduk;
    
        // Update data utama SPK
        $spk->update(array_merge($validated, [
            'jumlah_produk' => $jumlahProduk,
            'total_harga' => $totalHarga,
            'harga_per_jasa' => $harga_per_jasa,
            'harga_jasa_awal' => $harga_jasa_awal,
        ]));
    
        // Dapatkan semua ID warna yang dikirim dari request
        $warnaIds = collect($validated['warna'])->pluck('id_warna')->filter()->toArray();
    
        // Hapus warna lama yang tidak dikirim lagi dari frontend
        Warna::where('id_spk', $spk->id_spk)
            ->whereNotIn('id_warna', $warnaIds)
            ->delete();
    
        // Update warna yang ada atau tambahkan warna baru
        foreach ($validated['warna'] as $warna) {
            if (isset($warna['id_warna'])) {
                $existingWarna = Warna::find($warna['id_warna']);
                if ($existingWarna) {
                    $existingWarna->update([
                        'nama_warna' => $warna['nama_warna'],
                        'qty' => $warna['qty'],
                    ]);
                }
            } else {
                Warna::create([
                    'id_spk' => $spk->id_spk,
                    'nama_warna' => $warna['nama_warna'],
                    'qty' => $warna['qty'],
                ]);
            }
        }
    
        return response()->json([
            'message' => 'SPK berhasil diperbarui!',
            'data' => $spk
        ], 200);
    }
    
    

    // Menghapus SPK
    public function destroy($id)
    {
        $spk = SpkCmt::findOrFail($id);
        $spk->delete();

        return response()->json(['message' => 'SPK berhasil dihapus!']);
    }


    public function downloadPdf($id)
{
    $spk = SpkCmt::with('penjahit')->find($id);
    if (!$spk) {
        return response()->json(['error' => 'SPK not found'], 404);
    }

    $pdf = \App::make('snappy.pdf');
    $pdf->setOption('enable-local-file-access', true); // Tambahkan opsi ini

    $html = view('pdf.spk_cmt', compact('spk'))->render();
    return response($pdf->getOutputFromHtml($html), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="spk_cmt.pdf"'
    ]);
}
        


public function downloadStaffPdf($id)
{
    $spk = SpkCmt::with('penjahit')->find($id);
    if (!$spk) {
        return response()->json(['error' => 'SPK not found'], 404);
    }

    $pdf = \App::make('snappy.pdf');
    $pdf->setOption('enable-local-file-access', true); // Tambahkan opsi ini

    $html = view('pdf.spk_cmt_staff', compact('spk'))->render();
    return response($pdf->getOutputFromHtml($html), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="spk_cmt_staff.pdf"'
    ]);
}


public function updateDeadline(Request $request, $id)
    {
        $validated = $request->validate([
            'deadline' => ['required', 'date'],
            'keterangan' => ['required', 'string', 'max:255'],
        ]);
    
        $spk = SpkCmt::findOrFail($id);
        $deadlineLama = $spk->deadline;
    
        if ($spk->deadline != $validated['deadline']) {
            $spk->update(['deadline' => $validated['deadline']]);
    
            LogDeadline::create([
                'id_spk' => $spk->id_spk,
                'deadline_lama' => $deadlineLama,
                'deadline_baru' => $validated['deadline'],
                'tanggal_aktivitas' => now(),
                'keterangan' => $validated['keterangan'],
            ]);
        }
    
       // Kembalikan response dengan data SPK yang sudah diperbarui
    return response()->json([
        'message' => 'Deadline berhasil diperbarui',
        'data' => [
            'deadline' => $request->deadline,
            'keterangan' => $request->keterangan,
            'spk' => $spk,  // Menambahkan data SPK yang diperbarui
        ]
    ]);
    }
    
    public function getLogDeadline($id)
    {
        $logs = LogDeadline::where('id_spk', $id)
            ->orderBy('tanggal_aktivitas', 'desc')
            ->get();
            
        return response()->json($logs);
    }

    // SpkController.php
    public function getWarna($id)
    {
        // Ambil SPK berdasarkan ID
        $spk = SpkCmt::find($id);

        if (!$spk) {
            return response()->json(['message' => 'SPK not found'], 404);
        }

        // Ambil warna terkait dengan SPK ini
        $warna = $spk->warna; // Pastikan relasi 'warna' sudah didefinisikan dengan benar di model SpkCmt

        return response()->json(['warna' => $warna]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['Pending', 'In Progress', 'Completed'])],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
    
        $spk = SpkCmt::findOrFail($id);
        $statusLama = $spk->status;
    
        if ($spk->status !== $validated['status']) {
            $spk->setStatus($validated['status']);
            
           
            LogStatus::create([
                'id_spk' => $spk->id_spk,
                'status_lama' => $statusLama,
                'status_baru' => $validated['status'],
                'keterangan' => $validated['keterangan'] ?? null,
                'tanggal_aktivitas' => now(),
            ]);
        }
    
        return response()->json([
            'message' => 'Status berhasil diperbarui',
            'data' => [
                'status' => $spk->status,
                'keterangan' => $request->keterangan,
            ],
        ]);
    }
    
    public function getAllLogDeadlines()
    {
        // Ambil semua log deadline
        $logs = LogDeadline::orderBy('created_at', 'desc')->paginate(11);

       
        return response()->json($logs);
      
    }
    
    public function getAllLogStatus()
    {
      
        $logs = LogStatus::orderBy('created_at', 'desc')->paginate(11);
        return response()->json($logs);
    }

    public function debugDeadlines()
    {
        $spk = SpkCmt::all()->map(function ($item) {
            $deadline = \Carbon\Carbon::parse($item->deadline);
            return [
                'id_spk' => $item->id_spk,
                'deadline' => $item->deadline,
                'now' => now()->toDateString(),
                'sisa_hari' => $deadline->isPast() ? 0 : $deadline->diffInDays(now()),
            ];
        });

        return response()->json($spk);
    }

    public function getKinerjaCmt()
{
    $spks = SpkCmt::with('penjahit')->get();

    $penjahitKinerja = [];

    foreach ($spks as $spk) {
        $status = $spk->status;
    
        // Abaikan status 'In Progress'
        if ($status === 'In Progress') {
            continue;
        }
    
        $namaPenjahit = $spk->penjahit ? trim($spk->penjahit->nama_penjahit) : 'Tidak diketahui';
        $totalBarangDikirim = (int)$spk->total_barang_dikirim;
        $waktuPengerjaanTerakhir = $spk->waktu_pengerjaan_terakhir;
    
        // Log untuk memeriksa nilai atribut yang digunakan
        \Log::info("SPK ID: {$spk->id_spk}, Penjahit: {$namaPenjahit}, Status: {$status}, Barang Dikirim: {$totalBarangDikirim}, Waktu Pengerjaan Terakhir: {$waktuPengerjaanTerakhir}");
    
        // Tentukan kinerja berdasarkan waktu pengerjaan terakhir
        $kinerja = 0;
        if ($waktuPengerjaanTerakhir <= 7) {
            $kinerja = 100;
        } elseif ($waktuPengerjaanTerakhir <= 14) {
            $kinerja = 80;
        } elseif ($waktuPengerjaanTerakhir <= 21) {
            $kinerja = 60;
        } else {
            $kinerja = 40;
        }
    
        if (!isset($penjahitKinerja[$namaPenjahit])) {
            $penjahitKinerja[$namaPenjahit] = [
                'total_kinerja' => 0,
                'total_spk' => 0,
                'rata_rata' => 0,
                'kategori' => '',
                'spks' => [],
            ];
        }
    
        // Tambahkan detail SPK ke penjahit
        $penjahitKinerja[$namaPenjahit]['spks'][] = [
            'id_spk' => $spk->id_spk,
            'total_barang_dikirim' => $spk->total_barang_dikirim,
            'waktu_pengerjaan_terakhir' => $waktuPengerjaanTerakhir,
            'kinerja' => $kinerja,
            'status' => $spk->status
        ];
    
        // Menambahkan kinerja untuk rata-rata
        $penjahitKinerja[$namaPenjahit]['total_kinerja'] += $kinerja;
        $penjahitKinerja[$namaPenjahit]['total_spk']++;
    }
    

    // Kalkulasi rata-rata kinerja per penjahit
    foreach ($penjahitKinerja as $namaPenjahit => &$data) {
        $data['rata_rata'] = $data['total_spk'] > 0 ? $data['total_kinerja'] / $data['total_spk'] : 0;

        // Tentukan kategori berdasarkan rata-rata kinerja
        if ($data['rata_rata'] >= 90) {
            $data['kategori'] = 'A';
        } elseif ($data['rata_rata'] >= 80) {
            $data['kategori'] = 'B';
        } elseif ($data['rata_rata'] >= 70) {
            $data['kategori'] = 'C';
        } else {
            $data['kategori'] = 'D';
        }
    }

    return response()->json($penjahitKinerja);
}


    public function tentukanKategori($spk)
    {
        $status = $spk->status;
    
        if (in_array($status, ['Completed', 'Pending'])) {
            // Gunakan waktu_pengerjaan_terakhir untuk kategori
            $waktu = $spk->waktu_pengerjaan_terakhir;
        } else {
            // Jika status lain, langsung masukkan ke kategori D
            return null;
        }
    
        // Tentukan kategori berdasarkan durasi waktu
        if ($waktu <= 7) {
            return 'A';
        } elseif ($waktu > 7 && $waktu <= 14) {
            return 'B';
        } elseif ($waktu > 14 && $waktu <= 21) {
            return 'C';
        } else {
            return 'D';
        }
    }

    public function getKategoriCount()
{
    $spks = SpkCmt::with('penjahit')->get();

    $kategoriCount = [
        'A' => 0,
        'B' => 0,
        'C' => 0,
        'D' => 0,
    ];

    foreach ($spks as $spk) {
        $namaPenjahit = $spk->penjahit ? trim($spk->penjahit->nama_penjahit) : 'Tidak diketahui';
        $waktuPengerjaanTerakhir = $spk->waktu_pengerjaan_terakhir;

        // Tentukan kinerja berdasarkan waktu pengerjaan
        if ($waktuPengerjaanTerakhir <= 7) {
            $kategori = 'A';
        } elseif ($waktuPengerjaanTerakhir <= 14) {
            $kategori = 'B';
        } elseif ($waktuPengerjaanTerakhir <= 21) {
            $kategori = 'C';
        } else {
            $kategori = 'D';
        }

        $kategoriCount[$kategori]++;
    }

    return response()->json($kategoriCount);
}

public function getKategoriCountByPenjahit()
{
    $spks = SpkCmt::with('penjahit')->get();

    $penjahitKinerja = [];

    foreach ($spks as $spk) {
        $status = $spk->status;

        // Abaikan status 'In Progress'
        if ($status === 'In Progress') {
            continue;
        }

        $namaPenjahit = $spk->penjahit ? trim($spk->penjahit->nama_penjahit) : 'Tidak diketahui';
        $waktuPengerjaanTerakhir = $spk->waktu_pengerjaan_terakhir;

        if (!isset($penjahitKinerja[$namaPenjahit])) {
            $penjahitKinerja[$namaPenjahit] = [
                'total_kinerja' => 0,
                'total_spk' => 0,
                'kategori' => '',
            ];
        }

        // Tentukan kinerja berdasarkan waktu pengerjaan
        if ($waktuPengerjaanTerakhir <= 7) {
            $kinerja = 100;
        } elseif ($waktuPengerjaanTerakhir <= 14) {
            $kinerja = 80;
        } elseif ($waktuPengerjaanTerakhir <= 21) {
            $kinerja = 60;
        } else {
            $kinerja = 40;
        }

        $penjahitKinerja[$namaPenjahit]['total_kinerja'] += $kinerja;
        $penjahitKinerja[$namaPenjahit]['total_spk']++;
    }

    $kategoriCount = [
        'A' => 0,
        'B' => 0,
        'C' => 0,
        'D' => 0,
    ];

    // Tentukan kategori untuk setiap penjahit
    foreach ($penjahitKinerja as $namaPenjahit => $data) {
        $rataRata = $data['total_spk'] > 0 ? $data['total_kinerja'] / $data['total_spk'] : 0;

        if ($rataRata >= 90) {
            $kategori = 'A';
        } elseif ($rataRata >= 80) {
            $kategori = 'B';
        } elseif ($rataRata >= 70) {
            $kategori = 'C';
        } else {
            $kategori = 'D';
        }

        $penjahitKinerja[$namaPenjahit]['kategori'] = $kategori;
        $kategoriCount[$kategori]++;
    }

    // Hitung persentase
    $totalPenjahit = array_sum($kategoriCount);
    foreach ($kategoriCount as $key => $count) {
        $kategoriCount[$key] = [
            'count' => $count,
            'percentage' => $totalPenjahit > 0 ? round(($count / $totalPenjahit) * 100, 2) : 0,
        ];
    }

    return response()->json($kategoriCount);
}


public function getKemampuanCmt()
{
    $filterKategori = request()->input('kategori_sisa_produk');
    $filterKinerja = request()->input('kategori');

    // ✨ Tambahan untuk filter tanggal
    $startDate = request()->input('start_date'); // contoh: '2025-04-01'
    $endDate = request()->input('end_date');     // contoh: '2025-04-25'

    $penjahits = Penjahit::all();
    $kinerjaCmt = $this->getKinerjaCmt()->getData(true);
    $result = [];

    foreach ($penjahits as $penjahit) {
        if (empty($penjahit->nama_penjahit)) {
            continue;
        }

        $spks = SpkCmt::where('id_penjahit', $penjahit->id_penjahit)
            ->select('id_spk', 'jumlah_produk')
            ->get();

        $totalSisaProduk = 0;
        foreach ($spks as $spk) {
            $latestPengiriman = Pengiriman::where('id_spk', $spk->id_spk)
                ->latest('tanggal_pengiriman')
                ->first();

            if ($latestPengiriman) {
                $totalSisaProduk += $latestPengiriman->sisa_barang;
            } else {
                $totalSisaProduk += $spk->jumlah_produk;
            }
        }

        // ✨ Ini bagian penting: Query pengiriman dengan filter tanggal
        $pengirimanQuery = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
            ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
            ->select('pengiriman.id_spk', 'pengiriman.total_barang_dikirim', 'pengiriman.tanggal_pengiriman')
            ->orderBy('pengiriman.tanggal_pengiriman');

        if (!empty($startDate) && !empty($endDate)) {
            $pengirimanQuery->whereBetween('pengiriman.tanggal_pengiriman', [$startDate, $endDate]);
        }

        $pengiriman = $pengirimanQuery->get();

        $totalSpk = $pengiriman->unique('id_spk')->count();
        $pengirimanPerMinggu = $pengiriman->groupBy(function ($item) {
            return Carbon::parse($item->tanggal_pengiriman)->year . '-M' . Carbon::parse($item->tanggal_pengiriman)->weekOfYear;
        });

        $jumlahMinggu = $pengirimanPerMinggu->count();
        $totalBarang = $pengiriman->sum('total_barang_dikirim');
        $rataRataPerminggu = $jumlahMinggu > 0 ? $totalBarang / $jumlahMinggu : 0;

        // (lanjutan kode kategori, result, dll tetap sama seperti punyamu...)

        $kemampuanPerMinggu = $pengirimanPerMinggu->map(function ($items, $minggu) {
            return [
                'minggu' => $minggu,
                'data' => $items->map(function ($item) {
                    return [
                        'id_spk' => $item->id_spk,
                        'total_barang_dikirim' => $item->total_barang_dikirim,
                    ];
                })->values()
            ];
        })->values();

        $rataRata = $kinerjaCmt[$penjahit->nama_penjahit]['rata_rata'] ?? null;
        $kategori = $kinerjaCmt[$penjahit->nama_penjahit]['kategori'] ?? null;
        $spks = $kinerjaCmt[$penjahit->nama_penjahit]['spks'] ?? [];

        $kategoriSisaProduk = "Normal";
        if ($rataRataPerminggu == 0) {
            $kategoriSisaProduk = "-";
        } elseif ($totalSisaProduk > 2 * $rataRataPerminggu) {
            $kategoriSisaProduk = "Overload";
        } elseif ($totalSisaProduk >= $rataRataPerminggu && $totalSisaProduk <= 2 * $rataRataPerminggu) {
            $kategoriSisaProduk = "Underload";
        } else {
            $kategoriSisaProduk = "Normal";
        }
        if (empty($kategori)) {
            $kategori = "-";
        }

        if (!empty($filterKategori)) {
            if (strcasecmp($kategoriSisaProduk, $filterKategori) !== 0) {
                continue;
            }
        }

        if (!empty($filterKinerja)) {
            if (strcasecmp($kategori, $filterKinerja) !== 0) {
                continue;
            }
        }

        $result[$penjahit->nama_penjahit] = [
            'total_barang' => $totalBarang,
            'jumlah_minggu' => $jumlahMinggu,
            'rata_rata_perminggu' => round($rataRataPerminggu, 0),
            'total_spk' => $totalSpk,
            'total_sisa_produk' => $totalSisaProduk,
            'kategori_sisa_produk' => $kategoriSisaProduk,
            'rata_rata' => $rataRata,
            'kategori' => $kategori,
            'kemampuan_per_minggu' => $kemampuanPerMinggu,
            'spks' => $spks,
        ];
    }

    return response()->json($result);
}



}
