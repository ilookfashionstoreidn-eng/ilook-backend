<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\Bahan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\Aksesoris;
use Illuminate\Support\Facades\Storage;
use App\Models\ProdukUpdateHistory;
use Barryvdh\DomPDF\Facade\Pdf;


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
            $query->where('nama_produk', 'like', '%' . $search . '%');
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
        $validated = $request->validate([
            'nama_produk' => 'required|string|max:255',
            'kategori_produk' => 'required|string|max:255',
            'gambar_produk' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:25000',
            'jenis_produk' => 'required|string|max:255',

            // komponen
            'komponen' => 'array',
            'komponen.*.jenis_komponen' => 'required|string',
            'komponen.*.sumber_komponen' => 'required|in:bahan,aksesoris',

            'komponen.*.bahan_id' => 'nullable|required_if:komponen.*.sumber_komponen,bahan|exists:bahan,id',
            'komponen.*.aksesoris_id' => 'nullable|required_if:komponen.*.sumber_komponen,aksesoris|exists:aksesoris,id',

            'komponen.*.jumlah_bahan' => 'required|numeric|min:0.0001',

            // jasa & overhead
            'harga_jasa_cutting' => 'nullable|numeric',
            'harga_jasa_cmt' => 'nullable|numeric',
            'harga_jasa_aksesoris' => 'nullable|numeric',
            'harga_overhead' => 'nullable|numeric',
        ]);

        // Upload gambar
        if ($request->hasFile('gambar_produk')) {
            $file = $request->file('gambar_produk');
            $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file->getClientOriginalName());
            $file->storeAs('public/images', $fileName);
            $validated['gambar_produk'] = 'images/' . $fileName;
        }

        $validated['status_produk'] = 'Sementara';

        DB::transaction(function () use ($validated, $request, &$produk) {

            $produk = Produk::create($validated);
            $totalKomponen = 0;

            if ($request->has('komponen')) {
                foreach ($request->komponen as $komp) {

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
            }

            $hpp = $totalKomponen
                + ($produk->harga_jasa_cutting ?? 0)
                + ($produk->harga_jasa_cmt ?? 0)
                + ($produk->harga_jasa_aksesoris ?? 0)
                + ($produk->harga_overhead ?? 0);

            $produk->update(['hpp' => $hpp]);
        });
        return response()->json($produk->load(['komponen.bahan', 'komponen.aksesoris']), Response::HTTP_CREATED);
    }


    public function show(Produk $produk)
    {
        return response()->json($produk->load(['komponen.bahan', 'komponen.aksesoris']), Response::HTTP_OK);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama_produk' => 'required|string|max:255',
            'kategori_produk' => 'required|string|max:255',
            'jenis_produk' => 'required|string|max:255',
            'status_produk' => 'nullable|string',
            'gambar_produk' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:25000',

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

        DB::transaction(function () use ($validated, $request, $produk, $oldData) {

            // update produk utama
            $produk->update($validated);

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
            $produk->load('komponen'),
            Response::HTTP_OK
        );
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
