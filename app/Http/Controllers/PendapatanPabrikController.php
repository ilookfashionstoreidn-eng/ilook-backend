<?php

namespace App\Http\Controllers;

use App\Models\Pabrik;
use App\Models\PembelianBahan;
use App\Models\PendapatanPabrik;
use App\Models\PendapatanPabrikDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PendapatanPabrikController extends Controller
{
    /**
     * 1️⃣ LIST PABRIK + TOTAL HUTANG
     */
    public function index()
    {
        $data = Pabrik::withSum([
            'pembelianBahan as total_hutang' => function ($q) {
                $q->where('status_bayar', 'belum');
            }
        ], 'harga')->get();

        return response()->json([
            'success' => true,
            'data' => $data->map(function ($pabrik) {
                return [
                    'id' => $pabrik->id,
                    'nama_pabrik' => $pabrik->nama_pabrik,
                    'total_hutang' => $pabrik->total_hutang ?? 0,
                ];
            })
        ]);
    }

    /**
     * 2️⃣ DETAIL PEMBELIAN BELUM DIBAYAR PER PABRIK
     */
    public function show($pabrikId)
    {
        $pabrik = Pabrik::findOrFail($pabrikId);

        $pembelian = PembelianBahan::with(['spkBahan', 'bahan'])
            ->where('pabrik_id', $pabrikId)
            ->where('status_bayar', 'belum')
            ->get();

        return response()->json([
            'success' => true,
            'pabrik' => [
                'id' => $pabrik->id,
                'nama_pabrik' => $pabrik->nama_pabrik,
            ],
            'total_hutang' => $pembelian->sum('harga'),
            'pembelian' => $pembelian
        ]);
    }

    /**
     * 3️⃣ PROSES BAYAR
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pabrik_id' => 'required|exists:pabrik,id',
            'tanggal_bayar' => 'required|date',
            'tanggal_jatuh_tempo' => 'nullable|date',
            'keterangan' => 'nullable|string',
            'pembelian_ids' => 'required|array|min:1',
            'pembelian_ids.*' => 'exists:pembelian_bahan,id',
        ]);

        DB::beginTransaction();

        try {
            // ambil pembelian yang mau dibayar
            $pembelian = PembelianBahan::whereIn('id', $validated['pembelian_ids'])
                ->where('status_bayar', 'belum')
                ->get();

            if ($pembelian->isEmpty()) {
                throw new \Exception('Tidak ada pembelian yang bisa dibayar');
            }

            $totalBayar = $pembelian->sum('harga');

            // header pendapatan
            $pendapatan = PendapatanPabrik::create([
                'pabrik_id' => $validated['pabrik_id'],
                'tanggal_bayar' => $validated['tanggal_bayar'],
                'tanggal_jatuh_tempo' => $validated['tanggal_jatuh_tempo'] ?? null,
                'total_bayar' => $totalBayar,
                'keterangan' => $validated['keterangan'] ?? null,
            ]);

            // detail + update status pembelian
            foreach ($pembelian as $item) {
                PendapatanPabrikDetail::create([
                    'pendapatan_pabrik_id' => $pendapatan->id,
                    'pembelian_bahan_id' => $item->id,
                    'nominal' => $item->harga,
                ]);

                $item->update(['status_bayar' => 'sudah']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil',
                'data' => $pendapatan->load('detail.pembelianBahan')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Pembayaran gagal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 4️⃣ RIWAYAT PENDAPATAN YANG SUDAH DIBAYAR
     */
    public function history()
    {
        $perPage = request()->input('per_page', 10);

        $pendapatan = PendapatanPabrik::with(['pabrik', 'detail.pembelianBahan.bahan', 'detail.pembelianBahan.spkBahan'])
            ->orderBy('tanggal_bayar', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $pendapatan->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'pabrik_id' => $item->pabrik_id,
                'nama_pabrik' => $item->pabrik->nama_pabrik ?? '-',
                'tanggal_bayar' => $item->tanggal_bayar,
                'tanggal_jatuh_tempo' => $item->tanggal_jatuh_tempo,
                'total_bayar' => $item->total_bayar,
                'keterangan' => $item->keterangan,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'jumlah_pembelian' => $item->detail->count(),
                'detail' => $item->detail->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'pembelian_bahan_id' => $d->pembelian_bahan_id,
                        'nominal' => $d->nominal,
                        'bahan' => $d->pembelianBahan->bahan->nama_bahan ?? '-',
                        'spk_bahan_id' => $d->pembelianBahan->spkBahan->id ?? null,
                        'tanggal_kirim' => $d->pembelianBahan->tanggal_kirim ?? null,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $pendapatan
        ]);
    }
}
