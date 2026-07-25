<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HasilJasa;
use App\Models\SpkJasa;
use App\Models\SpkJasaStatusLog;


class HasilJasaController extends Controller
{
    private function buildBooleanSearch(string $search): string
    {
        $tokens = preg_split('/\s+/', trim($search));
        $tokens = array_filter(array_map(function ($token) {
            // Keep alnum only for boolean mode safety and tokenizer compatibility
            $cleaned = preg_replace('/[^\pL\pN]+/u', '', (string) $token);
            return mb_strlen($cleaned) >= 3 ? $cleaned : null;
        }, $tokens));

        if (empty($tokens)) {
            return '';
        }

        return implode(' ', array_map(fn($token) => '+' . $token . '*', $tokens));
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = max(10, min($perPage, 100));

        $search = trim((string) $request->get('search', ''));
        $sortBy = $request->get('sort_by', 'id');
        $sortDir = strtolower((string) $request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSort = ['id', 'tanggal', 'jumlah_hasil', 'jumlah_rusak', 'total_pendapatan'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'id';
        }

        $baseQuery = HasilJasa::query();

        if ($search !== '') {
            $searchMode = strtolower((string) $request->get('search_mode', 'smart'));
            $isNumeric = ctype_digit($search);
            $searchPrefix = $search . '%';
            $booleanSearch = $this->buildBooleanSearch($search);
            $useFullText = $searchMode !== 'contains' && $booleanSearch !== '';

            $baseQuery->where(function ($q) use ($search, $searchPrefix, $isNumeric, $useFullText, $booleanSearch) {
                if ($isNumeric) {
                    $numericValue = (int) $search;
                    $q->where('id', $numericValue)
                        ->orWhere('spk_jasa_id', $numericValue)
                        ->orWhere('jumlah_hasil', $numericValue)
                        ->orWhere('jumlah_rusak', $numericValue);
                } else {
                    // For non-numeric text, keep ID-like fields in prefix mode to reduce full scan.
                    $q->where('id', 'like', $searchPrefix)
                        ->orWhere('spk_jasa_id', 'like', $searchPrefix)
                        ->orWhere('jumlah_hasil', 'like', $searchPrefix)
                        ->orWhere('jumlah_rusak', 'like', $searchPrefix);
                }

                $q->orWhereHas('spkJasa.spkCuttingDistribusi', function ($distribusiQ) use ($search, $searchPrefix) {
                    $distribusiQ->where('kode_seri', 'like', $searchPrefix)
                        ->orWhere('kode_seri', 'like', "%{$search}%");
                });

                if ($useFullText) {
                    $q->orWhereHas('spkJasa.tukangJasa', function ($tukangQ) use ($booleanSearch) {
                        $tukangQ->whereRaw('MATCH(nama) AGAINST (? IN BOOLEAN MODE)', [$booleanSearch]);
                    })->orWhereHas('spkJasa.spkCuttingDistribusi.spkCutting.produk', function ($produkQ) use ($booleanSearch) {
                        $produkQ->whereRaw('MATCH(nama_produk) AGAINST (? IN BOOLEAN MODE)', [$booleanSearch]);
                    });
                } else {
                    $q->orWhereHas('spkJasa.tukangJasa', function ($tukangQ) use ($search, $searchPrefix) {
                        $tukangQ->where('nama', 'like', $searchPrefix)
                            ->orWhere('nama', 'like', "%{$search}%");
                    })->orWhereHas('spkJasa.spkCuttingDistribusi.spkCutting.produk', function ($produkQ) use ($search, $searchPrefix) {
                        $produkQ->where('nama_produk', 'like', $searchPrefix)
                            ->orWhere('nama_produk', 'like', "%{$search}%");
                    });
                }
            });
        }

        $summaryRaw = (clone $baseQuery)
            ->selectRaw('
                COUNT(*) as total_transaksi,
                COALESCE(SUM(jumlah_hasil), 0) as total_hasil,
                COALESCE(SUM(jumlah_rusak), 0) as total_rusak,
                COALESCE(SUM(total_pendapatan), 0) as total_pendapatan
            ')
            ->first();

        $totalHasil = (int) ($summaryRaw->total_hasil ?? 0);
        $totalRusak = (int) ($summaryRaw->total_rusak ?? 0);
        $totalOutput = $totalHasil + $totalRusak;
        $goodRate = $totalOutput > 0 ? round(($totalHasil / $totalOutput) * 100, 1) : 0.0;

        $data = (clone $baseQuery)->with([
            'spkJasa:id,tukang_jasa_id,spk_cutting_distribusi_id,status_pengambilan',
            'spkJasa.tukangJasa:id,nama',
            'spkJasa.spkCuttingDistribusi' => function ($q) {
                $q->select('id', 'spk_cutting_id', 'kode_seri');
            },
            'spkJasa.spkCuttingDistribusi.spkCutting' => function ($q) {
                $q->select('id', 'produk_id');
            },
            'spkJasa.spkCuttingDistribusi.spkCutting.produk:id,nama_produk'
        ])
            ->select(
                'id',
                'spk_jasa_id',
                'tanggal',
                'jumlah_hasil',
                'jumlah_rusak',
                'total_pendapatan'
            )
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

        return response()->json([
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'summary' => [
                'total_transaksi' => (int) ($summaryRaw->total_transaksi ?? 0),
                'total_hasil' => $totalHasil,
                'total_rusak' => $totalRusak,
                'total_pendapatan' => (float) ($summaryRaw->total_pendapatan ?? 0),
                'good_rate' => $goodRate,
            ],
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'spk_jasa_id'     => 'required|exists:spk_jasa,id',
            'tanggal'         => 'required|date',
            'jumlah_hasil'    => 'required|integer|min:0',
            'jumlah_rusak'    => 'nullable|integer|min:0',
            'bukti_transfer'  => 'nullable|file|mimes:jpg,jpeg,png,pdf',
        ]);

        $validated['jumlah_rusak'] = $validated['jumlah_rusak'] ?? 0;

        // Upload bukti di luar transaksi (I/O, tidak perlu ikut dikunci).
        if ($request->hasFile('bukti_transfer')) {
            $validated['bukti_transfer'] =
                $request->file('bukti_transfer')->store('bukti_transfer_jasa', 'public');
        }

        try {
            $hasil = \DB::transaction(function () use ($validated) {
                // Lock baris SPK Jasa supaya dua request "tambah hasil" yang
                // bersamaan untuk SPK yang sama tidak bisa sama-sama lolos
                // pengecekan kuota sebelum salah satunya commit (race condition
                // yang bisa membuat total hasil melebihi jumlah SPK).
                $spkJasa = SpkJasa::whereKey($validated['spk_jasa_id'])->lockForUpdate()->firstOrFail();

                $totalSebelumnya = HasilJasa::where('spk_jasa_id', $spkJasa->id)
                    ->selectRaw('COALESCE(SUM(jumlah_hasil + jumlah_rusak), 0) as total')
                    ->value('total');

                $totalBaru = $totalSebelumnya
                    + $validated['jumlah_hasil']
                    + $validated['jumlah_rusak'];

                if ($totalBaru > $spkJasa->jumlah) {
                    throw new \RuntimeException('Total hasil melebihi jumlah SPK Jasa');
                }

                $validated['total_pendapatan'] =
                    $validated['jumlah_hasil'] * ($spkJasa->harga_per_pcs ?? 0);
                $validated['status_bayar'] = 'belum_dibayar';
                $validated['pendapatan_jasa_id'] = null;

                $hasilRow = HasilJasa::create($validated);

                $totalOk = HasilJasa::where('spk_jasa_id', $spkJasa->id)->sum('jumlah_hasil');
                $totalRusak = HasilJasa::where('spk_jasa_id', $spkJasa->id)->sum('jumlah_rusak');

                if (($totalOk + $totalRusak) >= $spkJasa->jumlah) {
                    $spkJasa->update([
                        'status_pengambilan' => 'selesai'
                    ]);

                    SpkJasaStatusLog::create([
                        'spk_jasa_id' => $spkJasa->id,
                        'status'      => 'selesai',
                        'keterangan'  => 'Hasil jasa telah terpenuhi'
                    ]);
                }

                return $hasilRow;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Hasil Jasa berhasil ditambahkan',
            'data' => $hasil
        ], 201);
    }
}
