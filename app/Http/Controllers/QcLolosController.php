<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\QcScanLolos;

class QcLolosController extends Controller
{
    /**
     * Ambil semua data scan lolos, di-group by nomor_seri + sku,
     * dengan total jumlah dan count scan per group.
     */
    public function index(Request $request)
    {
        $grouped = DB::table('qc_scan_lolos')
            ->select(
                'nomor_seri',
                'sku',
                DB::raw('SUM(jumlah) as total_jumlah'),
                DB::raw('COUNT(*) as jumlah_scan'),
                DB::raw('MAX(created_at) as last_scan')
            )
            ->groupBy('nomor_seri', 'sku')
            ->orderBy('last_scan', 'desc')
            ->get();

        $totalItem = $grouped->sum('total_jumlah');
        $totalScan = DB::table('qc_scan_lolos')->count();

        return response()->json([
            'data'        => $grouped,
            'total_item'  => $totalItem,
            'total_scan'  => $totalScan,
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
