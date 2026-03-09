<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\QcScanLolos;

class QcLolosController extends Controller
{
    /**
     * Ringkasan metrik QC Lolos untuk dashboard/header.
     */
    public function index(Request $request)
    {
        $totalItem = DB::table('qc_scan_lolos')->sum('jumlah');
        $totalScan = DB::table('qc_scan_lolos')->count();
        $totalGroup = DB::table(DB::raw('(SELECT nomor_seri, sku FROM qc_scan_lolos GROUP BY nomor_seri, sku) as g'))
            ->count();

        return response()->json([
            'total_item'  => $totalItem,
            'total_scan'  => $totalScan,
            'total_group' => $totalGroup,
        ]);
    }

    /**
     * Report QC Lolos dengan server-side pagination + filter.
     */
    public function report(Request $request)
    {
        $validated = $request->validate([
            'page'        => 'nullable|integer|min:1',
            'per_page'    => 'nullable|integer|min:1|max:200',
            'search'      => 'nullable|string|max:100',
            'sku'         => 'nullable|string|max:100',
            'time_filter' => 'nullable|in:all,today,hour',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);
        $search = trim((string) ($validated['search'] ?? ''));
        $sku = trim((string) ($validated['sku'] ?? ''));
        $timeFilter = $validated['time_filter'] ?? 'all';

        $groupedBase = DB::table('qc_scan_lolos')
            ->select(
                'nomor_seri',
                'sku',
                DB::raw('SUM(jumlah) as total_jumlah'),
                DB::raw('COUNT(*) as jumlah_scan'),
                DB::raw('MAX(created_at) as last_scan')
            )
            ->groupBy('nomor_seri', 'sku');

        $query = DB::query()->fromSub($groupedBase, 'q');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nomor_seri', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%');
            });
        }

        if ($sku !== '' && strtolower($sku) !== 'all') {
            $query->where('sku', strtoupper($sku));
        }

        if ($timeFilter === 'hour') {
            $query->where('last_scan', '>=', now()->subHour());
        } elseif ($timeFilter === 'today') {
            $query->whereDate('last_scan', now()->toDateString());
        }

        $query->orderByDesc('last_scan');

        $report = $query->paginate($perPage);

        $skuOptions = DB::table('qc_scan_lolos')
            ->select('sku')
            ->distinct()
            ->orderBy('sku')
            ->pluck('sku');

        return response()->json([
            'data' => $report->items(),
            'meta' => [
                'current_page' => $report->currentPage(),
                'per_page' => $report->perPage(),
                'last_page' => $report->lastPage(),
                'total' => $report->total(),
            ],
            'filters' => [
                'search' => $search,
                'sku' => $sku,
                'time_filter' => $timeFilter,
            ],
            'sku_options' => $skuOptions,
        ]);
    }

    /**
     * Proses scan barcode produk.
     * Barcode format: "SKU | NOMOR_SERI"  (sesuai QR yang digenerate SeriController)
     * Atau bisa kirim nomor_seri + sku secara terpisah.
     */
    public function scan(Request $request)
    {
        // Support dua mode:
        // 1. barcode string: "SKU | NOMOR_SERI"
        // 2. field terpisah: nomor_seri + sku

        $nomor_seri = null;
        $sku        = null;

        if ($request->filled('barcode')) {
            $barcode = trim($request->barcode);

            // Coba parse format "SKU | NOMOR_SERI"
            if (str_contains($barcode, '|')) {
                $parts = array_map('trim', explode('|', $barcode, 2));
                $sku        = strtoupper($parts[0]);
                $nomor_seri = $parts[1];
            } else {
                // Tidak ada separator, anggap seluruhnya nomor_seri
                // dan minta sku di field terpisah
                $nomor_seri = $barcode;
                $sku        = strtoupper(trim($request->input('sku', '')));
            }
        } else {
            $request->validate([
                'nomor_seri' => 'required|string',
                'sku'        => 'required|string',
            ]);
            $nomor_seri = trim($request->nomor_seri);
            $sku        = strtoupper(trim($request->sku));
        }

        if (!$nomor_seri || !$sku) {
            return response()->json([
                'message' => 'Format barcode tidak valid. Pastikan barcode mengandung SKU dan Nomor Seri.',
            ], 422);
        }

        try {
            $scan = QcScanLolos::create([
                'nomor_seri' => $nomor_seri,
                'sku'        => $sku,
                'jumlah'     => 1,
            ]);

            // Ambil akumulasi untuk nomor_seri + sku ini
            $akumulasi = DB::table('qc_scan_lolos')
                ->where('nomor_seri', $nomor_seri)
                ->where('sku', $sku)
                ->select(
                    DB::raw('SUM(jumlah) as total_jumlah'),
                    DB::raw('COUNT(*) as jumlah_scan')
                )
                ->first();

            return response()->json([
                'message'    => 'Scan berhasil. Barang lolos QC.',
                'nomor_seri' => $nomor_seri,
                'sku'        => $sku,
                'total_jumlah' => $akumulasi->total_jumlah,
                'jumlah_scan'  => $akumulasi->jumlah_scan,
                'scan_id'    => $scan->id,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan scan.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus satu entri scan (undo scan terakhir untuk nomor_seri + sku).
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'nomor_seri' => 'required|string',
            'sku'        => 'required|string',
        ]);

        $last = QcScanLolos::where('nomor_seri', $request->nomor_seri)
            ->where('sku', $request->sku)
            ->latest()
            ->first();

        if (!$last) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $last->delete();

        return response()->json(['message' => 'Scan terakhir berhasil dihapus.']);
    }
}
