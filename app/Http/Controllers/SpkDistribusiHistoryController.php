<?php

namespace App\Http\Controllers;

use App\Models\SpkCuttingDistribusi;
use App\Models\SpkCuttingStatusLog;
use App\Models\SpkJasa;
use App\Models\SpkCmt;
use App\Models\SpkJasaStatusLog;
use App\Models\LogStatusSpkCmt;
use App\Models\PembelianBahan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SpkDistribusiHistoryController extends Controller
{
   public function history($distribusiId)
    {
        $distribusi = SpkCuttingDistribusi::findOrFail($distribusiId);

        $timeline = collect();

        /**
         * =========================
         * SPK CUTTING
         * =========================
         */
        $cuttingLogs = SpkCuttingStatusLog::where(
            'spk_cutting_id',
            $distribusi->spk_cutting_id
        )->orderBy('created_at')->get();

        foreach ($cuttingLogs as $log) {
            $timeline->push([
                'waktu'      => Carbon::parse($log->created_at),
                'tipe'       => 'cutting',
                'status'     => $log->status,
                'keterangan' => $log->keterangan,
                'ref_id'     => $log->spk_cutting_id,
            ]);
        }

        /**
         * =========================
         * SPK JASA
         * =========================
         */
        $jasa = SpkJasa::with('statusLogs')
            ->where('spk_cutting_distribusi_id', $distribusi->id)
            ->first();

        if ($jasa) {
            foreach ($jasa->statusLogs as $log) {
                $timeline->push([
                    'waktu'      => Carbon::parse($log->created_at),
                    'tipe'       => 'jasa',
                    'status'     => $log->status,
                    'keterangan' => null,
                    'ref_id'     => $jasa->id,
                ]);
            }
        }

        /**
         * =========================
         * SPK CMT
         * =========================
         */
        $cmtList = SpkCmt::where(function ($q) use ($distribusi) {
            $q->where(function ($q2) use ($distribusi) {
                $q2->where('source_type', 'cutting')
                   ->where('source_id', $distribusi->id);
            })->orWhere(function ($q2) use ($distribusi) {
                $q2->where('source_type', 'jasa')
                   ->whereIn('source_id', function ($sub) use ($distribusi) {
                       $sub->select('id')
                           ->from('spk_jasa')
                           ->where('spk_cutting_distribusi_id', $distribusi->id);
                   });
            });
        })->get();

        foreach ($cmtList as $cmt) {
            $logs = LogStatusSpkCmt::where('spk_cmt_id', $cmt->id_spk)
                ->orderBy('created_at')
                ->get();

            foreach ($logs as $log) {
                $timeline->push([
                    'waktu'      => Carbon::parse($log->created_at),
                    'tipe'       => 'cmt',
                    'status'     => $log->status,
                    'keterangan' => $log->keterangan,
                    'ref_id'     => $cmt->id_spk,
                ]);
            }
        }

        /**
         * =========================
         * SORT & RESPONSE
         * =========================
         */
        $timeline = $timeline
            ->sortBy('waktu')
            ->values()
            ->map(function ($item) {
                $item['waktu'] = $item['waktu']->format('Y-m-d H:i:s');
                return $item;
            });

        $additionalInfo = $this->getAdditionalInfo($distribusi);

        return response()->json([
            'distribusi_id' => $distribusi->id,
            'kode_seri'     => $distribusi->kode_seri,
            'history'       => $timeline,
            'nama_tukang_cutting' => $additionalInfo['nama_tukang_cutting'],
            'nama_tukang_jasa' => $additionalInfo['nama_tukang_jasa'],
            'nama_cmt' => $additionalInfo['nama_cmt'],
            'bahan_pabrik' => $additionalInfo['bahan_pabrik'],
            'bahan_gudang' => $additionalInfo['bahan_gudang'],
        ]);
    }


    public function historyBySpkCutting($spkCuttingId)
{
    $distribusiList = SpkCuttingDistribusi::where(
        'spk_cutting_id',
        $spkCuttingId
    )->get();

    $result = [];

    foreach ($distribusiList as $distribusi) {
        $result[] = [
            'distribusi_id' => $distribusi->id,
            'kode_seri'     => $distribusi->kode_seri,
            'jumlah_produk' => $distribusi->jumlah_produk,
            'history'       => $this->buildHistory($distribusi),
        ];
    }

    return response()->json([
        'spk_cutting_id' => $spkCuttingId,
        'distribusi'     => $result
    ]);
}

public function historyAll(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('q', ''));

        $query = SpkCuttingDistribusi::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('kode_seri', 'like', "%{$search}%")
                  ->orWhere('spk_cutting_id', 'like', "%{$search}%");
            });
        }

        $distribusiList = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $result = [];

        foreach ($distribusiList->items() as $distribusi) {
            $additionalInfo = $this->getAdditionalInfo($distribusi);
            
            $result[] = [
                'distribusi_id' => $distribusi->id,
                'kode_seri'     => $distribusi->kode_seri,
                'jumlah_produk' => $distribusi->jumlah_produk,
                'spk_cutting_id'=> $distribusi->spk_cutting_id,
                'created_at'    => optional($distribusi->created_at)->format('Y-m-d H:i:s'),
                'history'       => $this->buildHistory($distribusi),
                'nama_tukang_cutting' => $additionalInfo['nama_tukang_cutting'],
                'nama_tukang_jasa' => $additionalInfo['nama_tukang_jasa'],
                'nama_cmt' => $additionalInfo['nama_cmt'],
                'bahan_pabrik' => $additionalInfo['bahan_pabrik'],
                'bahan_gudang' => $additionalInfo['bahan_gudang'],
            ];
        }

        return response()->json([
            'data' => $result,
            'meta' => [
                'current_page' => $distribusiList->currentPage(),
                'last_page' => $distribusiList->lastPage(),
                'total' => $distribusiList->total(),
                'per_page' => $distribusiList->perPage(),
            ],
        ]);
    }

    private function buildHistory(SpkCuttingDistribusi $distribusi)
    {
        $timeline = collect();

        /**
         * CUTTING
         */
        $cuttingLogs = SpkCuttingStatusLog::where(
            'spk_cutting_id',
            $distribusi->spk_cutting_id
        )->get();

        foreach ($cuttingLogs as $log) {
            $timeline->push([
                'waktu'      => Carbon::parse($log->created_at),
                'tipe'       => 'cutting',
                'status'     => $log->status,
                'keterangan' => $log->keterangan,
            ]);
        }

        /**
         * JASA
         */
        $jasa = SpkJasa::with('statusLogs')
            ->where('spk_cutting_distribusi_id', $distribusi->id)
            ->first();

        if ($jasa) {
            foreach ($jasa->statusLogs as $log) {
                $timeline->push([
                    'waktu'      => Carbon::parse($log->created_at),
                    'tipe'       => 'jasa',
                    'status'     => $log->status,
                    'keterangan' => null,
                ]);
            }
        }

        /**
         * CMT
         */
        $cmtList = SpkCmt::where(function ($q) use ($distribusi) {
            $q->where([
                ['source_type', 'cutting'],
                ['source_id', $distribusi->id],
            ])->orWhere(function ($q2) use ($distribusi) {
                $q2->where('source_type', 'jasa')
                   ->whereIn('source_id', function ($sub) use ($distribusi) {
                       $sub->select('id')
                           ->from('spk_jasa')
                           ->where('spk_cutting_distribusi_id', $distribusi->id);
                   });
            });
        })->get();

        foreach ($cmtList as $cmt) {
            $logs = LogStatusSpkCmt::where('spk_cmt_id', $cmt->id_spk)->get();

            foreach ($logs as $log) {
                $timeline->push([
                    'waktu'      => Carbon::parse($log->created_at),
                    'tipe'       => 'cmt',
                    'status'     => $log->status,
                    'keterangan' => $log->keterangan,
                ]);
            }
        }

        return $timeline
            ->sortBy('waktu')
            ->values()
            ->map(fn ($i) => [
                ...$i,
                'waktu' => $i['waktu']->format('Y-m-d H:i:s')
            ]);
    }

    private function getAdditionalInfo(SpkCuttingDistribusi $distribusi)
    {
        // Load relasi yang diperlukan
        $distribusi->load([
            'spkCutting.tukangCutting',
            'spkJasa.tukangJasa',
            'spkCutting.bagian.bahan.bahan',
        ]);

        // Nama Tukang Cutting
        $namaTukangCutting = $distribusi->spkCutting && $distribusi->spkCutting->tukangCutting 
            ? $distribusi->spkCutting->tukangCutting->nama_tukang_cutting 
            : '-';

        // Nama Tukang Jasa
        $namaTukangJasa = '-';
        if ($distribusi->spkJasa && $distribusi->spkJasa->tukangJasa) {
            $namaTukangJasa = $distribusi->spkJasa->tukangJasa->nama ?? '-';
        }

        // Nama CMT (Penjahit)
        $namaCmtList = [];
        $cmtList = SpkCmt::with('penjahit')
            ->where(function ($q) use ($distribusi) {
                $q->where([
                    ['source_type', 'cutting'],
                    ['source_id', $distribusi->id],
                ])->orWhere(function ($q2) use ($distribusi) {
                    $q2->where('source_type', 'jasa')
                       ->whereIn('source_id', function ($sub) use ($distribusi) {
                           $sub->select('id')
                               ->from('spk_jasa')
                               ->where('spk_cutting_distribusi_id', $distribusi->id);
                       });
                });
            })->get();

        foreach ($cmtList as $cmt) {
            if ($cmt->penjahit) {
                $namaCmtList[] = $cmt->penjahit->nama_penjahit;
            }
        }
        $namaCmt = !empty($namaCmtList) ? implode(', ', array_unique($namaCmtList)) : '-';

        // Bahan dari Pabrik dan Gudang
        $bahanPabrikList = [];
        $bahanGudangList = [];
        
        // Ambil bahan dari SpkCutting melalui bagian
        if ($distribusi->spkCutting && $distribusi->spkCutting->bagian) {
            $bahanIds = [];
            
            // Kumpulkan semua bahan_id dari SpkCuttingBagian -> SpkCuttingBahan
            foreach ($distribusi->spkCutting->bagian as $bagian) {
                if ($bagian->bahan) {
                    foreach ($bagian->bahan as $spkBahan) {
                        if ($spkBahan->bahan_id) {
                            $bahanIds[] = $spkBahan->bahan_id;
                        }
                    }
                }
            }

            // Ambil PembelianBahan yang terkait dengan bahan-bahan tersebut
            if (!empty($bahanIds)) {
                $pembelianBahanList = PembelianBahan::with(['pabrik', 'gudang', 'bahan'])
                    ->whereIn('bahan_id', array_unique($bahanIds))
                    ->get();

                foreach ($pembelianBahanList as $pembelianBahan) {
                    if ($pembelianBahan->bahan) {
                        $namaBahan = $pembelianBahan->bahan->nama_bahan ?? '-';
                        
                        if ($pembelianBahan->pabrik && $pembelianBahan->pabrik->nama_pabrik) {
                            $bahanPabrikList[] = $namaBahan . ' (' . $pembelianBahan->pabrik->nama_pabrik . ')';
                        }
                        
                        if ($pembelianBahan->gudang && $pembelianBahan->gudang->nama_gudang) {
                            $bahanGudangList[] = $namaBahan . ' (' . $pembelianBahan->gudang->nama_gudang . ')';
                        }
                    }
                }
            }
        }

        $bahanPabrik = !empty($bahanPabrikList) ? implode(', ', array_unique($bahanPabrikList)) : '-';
        $bahanGudang = !empty($bahanGudangList) ? implode(', ', array_unique($bahanGudangList)) : '-';

        return [
            'nama_tukang_cutting' => $namaTukangCutting,
            'nama_tukang_jasa' => $namaTukangJasa,
            'nama_cmt' => $namaCmt,
            'bahan_pabrik' => $bahanPabrik,
            'bahan_gudang' => $bahanGudang,
        ];
    }

}
