<?php

namespace App\Http\Controllers;

use App\Models\SpkCuttingDistribusi;
use App\Models\SpkCuttingStatusLog;
use App\Models\SpkJasa;
use App\Models\SpkCmt;
use App\Models\SpkJasaStatusLog;
use App\Models\LogStatusSpkCmt;
use Carbon\Carbon;

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

        return response()->json([
            'distribusi_id' => $distribusi->id,
            'kode_seri'     => $distribusi->kode_seri,
            'history'       => $timeline,
        ]);
    }
}
