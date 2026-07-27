<?php

namespace App\Http\Controllers;

use App\Models\Seri;
use App\Models\Sku;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SeriController extends Controller
{
    public function index()
    {
        // Mode list penuh untuk dropdown/search
        if (request()->boolean('all')) {
            $query = Seri::select('id', 'nomor_seri', 'sku', 'sku_id', 'jumlah', 'created_at');

            if (request()->has('search') && request()->filled('search')) {
                $search = request()->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('nomor_seri', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            } else {
                // Limit to prevent crashing when database has 200k items
                $query->limit(100);
            }

            $seriList = $query->orderBy('nomor_seri')->get();

            $uniqueNomorSeris = $seriList->pluck('nomor_seri')->unique()->all();
            $scannedBarcodesMap = [];

            // Chunk to avoid massive database queries when there are many serials
            foreach (array_chunk($uniqueNomorSeris, 50) as $chunk) {
                $qLogs = \Illuminate\Support\Facades\DB::table('gudang_produk_activity_logs')
                    ->where('type', 'placement');
                
                $qLogs->where(function($q) use ($chunk) {
                    foreach ($chunk as $ns) {
                        $q->orWhere('notes', 'like', "%Kode seri: {$ns}.%");
                    }
                });

                $notesList = $qLogs->pluck('notes');

                foreach ($notesList as $note) {
                    if (preg_match('/Kode seri:\s*([^\s,|]+)/i', $note, $matches)) {
                        $barcodeKey = trim($matches[1]);
                        $barcodeKey = rtrim($barcodeKey, '., ');
                        if (preg_match('/^(.+)\.(\d+)$/', $barcodeKey, $m)) {
                            $ns = strtoupper(trim($m[1]));
                            $seq = (int)$m[2];
                            $scannedBarcodesMap["{$ns}.{$seq}"] = true;
                        }
                    }
                }
            }

            $unfinishedOnly = request()->boolean('unfinished') || request()->boolean('unfinished_only');
            $result = [];

            // Compute running sums in memory using one query for the matching nomor_seri values
            $allSerisForSum = [];
            if (!empty($uniqueNomorSeris)) {
                $allSerisForSum = Seri::whereIn('nomor_seri', $uniqueNomorSeris)
                    ->orderBy('id')
                    ->get(['id', 'nomor_seri', 'jumlah']);
            }
                
            $runningSums = [];
            $groupedSeris = collect($allSerisForSum)->groupBy('nomor_seri');
            foreach ($groupedSeris as $ns => $items) {
                $sum = 0;
                foreach ($items as $item) {
                    $runningSums[$item->id] = $sum;
                    $sum += (int)$item->jumlah;
                }
            }

            $grouped = $seriList->groupBy('nomor_seri');
            foreach ($grouped as $ns => $items) {
                $sortedItems = $items->sortBy('id');
                foreach ($sortedItems as $item) {
                    $nomorAwalCek = $runningSums[$item->id] ?? 0;
                    $scannedCount = 0;
                    $jumlah = max(1, (int)$item->jumlah);
                    $nsUpper = strtoupper($item->nomor_seri);
                    for ($i = 1; $i <= $jumlah; $i++) {
                        $seq = $nomorAwalCek + $i;
                        if (isset($scannedBarcodesMap["{$nsUpper}.{$seq}"])) {
                            $scannedCount++;
                        }
                    }

                    $item->scanned_count = $scannedCount;
                    $isFinished = ($scannedCount >= $jumlah);

                    if (!$unfinishedOnly || !$isFinished) {
                        $result[] = $item;
                    }
                }
            }

            // Sort by nomor_seri
            usort($result, function($a, $b) {
                return strcmp($a->nomor_seri, $b->nomor_seri);
            });

            return response()->json([
                'data' => $result,
            ]);
        }

        // Default: pagination untuk halaman manajemen
        $query = Seri::query();
        
        if (request()->has('search') && request()->filled('search')) {
            $search = request()->input('search');
            $query->where(function($q) use ($search) {
                $q->where('nomor_seri', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }
        
        $query->orderBy('created_at', 'desc');
        $statusScan = request()->input('status_scan', 'all');

        if ($statusScan !== 'all') {
            // Manual pagination for scanned/unscanned tabs
            $allSeris = $query->get();
            $uniqueNomorSeris = $allSeris->pluck('nomor_seri')->unique()->all();
            
            $scannedBarcodesMap = [];
            if (!empty($uniqueNomorSeris)) {
                $activities = \Illuminate\Support\Facades\DB::table('gudang_produk_activity_logs')
                    ->where('type', 'placement')
                    ->pluck('notes');

                foreach ($activities as $note) {
                    if (preg_match('/Kode seri:\s*([^\s,|]+)/i', $note, $matches)) {
                        $barcodeKey = trim($matches[1]);
                        $barcodeKey = rtrim($barcodeKey, '., ');
                        $scannedBarcodesMap[$barcodeKey] = true;
                    }
                }
            }

            $runningSums = [];
            $groupedSeris = collect($allSeris)->groupBy(function($item) {
                return $item->nomor_seri . '|' . $item->sku;
            });
            foreach ($groupedSeris as $ns => $items) {
                $items = $items->sortBy('id');
                $sum = 0;
                foreach ($items as $item) {
                    $runningSums[$item->id] = $sum;
                    $sum += (int)$item->jumlah;
                }
            }

            $filtered = $allSeris->filter(function ($item) use ($runningSums, $scannedBarcodesMap, $statusScan) {
                $nomorSeriBase = $item->nomor_seri;
                $jumlah = max(1, (int)$item->jumlah);
                $nomorAwalCek = $runningSums[$item->id] ?? 0;
                $scannedCount = 0;
                $scannedDetails = [];

                for ($i = 1; $i <= $jumlah; $i++) {
                    $barcode = $nomorSeriBase . '.' . ($nomorAwalCek + $i);
                    if (isset($scannedBarcodesMap[$barcode])) {
                        $scannedCount++;
                        $scannedDetails[] = [
                            'barcode' => $barcode,
                            'source' => 'Stok Awal Gudang'
                        ];
                    }
                }
                
                $item->scanned_count = $scannedCount;
                $item->scanned_details = $scannedDetails;

                if ($statusScan === 'scanned') return $scannedCount > 0;
                if ($statusScan === 'unscanned') return $scannedCount === 0;
                return true;
            })->values();

            $perPage = request()->input('per_page', 30);
            $page = request()->input('page', 1);
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $filtered->forPage($page, $perPage)->values(),
                $filtered->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            return response()->json($paginator);
        }

        // Default pagination if status_scan is 'all'
        $perPage = request()->input('per_page', 30);
        $seri = $query->paginate($perPage);

        $uniqueNomorSeris = $seri->getCollection()->pluck('nomor_seri')->unique()->all();
        $scannedBarcodesMap = [];

        if (!empty($uniqueNomorSeris)) {
            $activities = \Illuminate\Support\Facades\DB::table('gudang_produk_activity_logs')
                ->where('type', 'placement')
                ->where(function($q) use ($uniqueNomorSeris) {
                    foreach ($uniqueNomorSeris as $ns) {
                        $q->orWhere('notes', 'like', "%Kode seri: {$ns}.%");
                    }
                })
                ->pluck('notes');

            foreach ($activities as $note) {
                if (preg_match('/Kode seri:\s*([^\s,|]+)/i', $note, $matches)) {
                    $barcodeKey = trim($matches[1]);
                    $barcodeKey = rtrim($barcodeKey, '., ');
                    $scannedBarcodesMap[$barcodeKey] = true;
                }
            }
        }

        $allSerisForSum = [];
        if (!empty($uniqueNomorSeris)) {
            $allSerisForSum = Seri::whereIn('nomor_seri', $uniqueNomorSeris)
                ->orderBy('id')
                ->get(['id', 'nomor_seri', 'jumlah']);
        }
        
        $runningSums = [];
        $groupedSeris = collect($allSerisForSum)->groupBy('nomor_seri');
        foreach ($groupedSeris as $ns => $items) {
            $sum = 0;
            foreach ($items as $item) {
                $runningSums[$item->id] = $sum;
                $sum += (int)$item->jumlah;
            }
        }

        $seri->getCollection()->transform(function ($item) use ($runningSums, $scannedBarcodesMap) {
            $nomorSeriBase = $item->nomor_seri;
            $jumlah = max(1, (int)$item->jumlah);
            $nomorAwalCek = $runningSums[$item->id] ?? 0;
            $scannedDetails = [];
            $scannedCount = 0;

            for ($i = 1; $i <= $jumlah; $i++) {
                $barcode = $nomorSeriBase . '.' . ($nomorAwalCek + $i);

                if (isset($scannedBarcodesMap[$barcode])) {
                    $scannedDetails[] = [
                        'barcode' => $barcode,
                        'source' => 'Stok Awal Gudang'
                    ];
                    $scannedCount++;
                }
            }

            $item->scanned_count = $scannedCount;
            $item->scanned_details = $scannedDetails;

            return $item;
        });

        return response()->json($seri);
    }

    public function lookup(Request $request)
    {
        $search = $request->input('search');
        if (empty($search)) {
            return response()->json(['message' => 'Query search wajib diisi.'], 400);
        }

        $serial = $search;
        $sku = null;
        if (str_contains($search, '|')) {
            $parts = array_map('trim', explode('|', $search, 2));
            $sku = $parts[0];
            $serial = $parts[1] ?? $search;
        }

        $lastDot = strrpos($serial, '.');
        if ($lastDot !== false) {
            $possibleNumber = substr($serial, $lastDot + 1);
            $possibleBase = substr($serial, 0, $lastDot);
            if (is_numeric($possibleNumber) && $possibleBase !== '') {
                $serial = $possibleBase;
            }
        }

        $query = Seri::where('nomor_seri', strtoupper(trim($serial)));
        if ($sku) {
            $query->where('sku', strtoupper(trim($sku)));
        }
        $seri = $query->first();

        if (!$seri) {
            return response()->json(['message' => 'Nomor seri tidak ditemukan.'], 404);
        }

        return response()->json($seri);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nomor_seri' => 'required',
            'sku' => 'required',
            'jumlah' => 'required|integer|min:1',
            'jenis_seri' => 'nullable|in:opname,stok_awal,barang_masuk,tanpa_seri,return',
            'source' => 'nullable|string',
        ]);

        $jenisSeri = $validated['jenis_seri'] ?? null;
        if ($jenisSeri === 'stok_awal') {
            $nomorSeri = $this->buildStockAwalNomorSeri($validated['sku']);
        } elseif ($jenisSeri === 'tanpa_seri') {
            $nomorSeri = $this->buildTanpaSeriNomorSeri($validated['sku']);
        } elseif ($jenisSeri === 'return') {
            $nomorSeri = $this->buildReturnNomorSeri($validated['sku']);
        } else {
            $nomorSeri = strtoupper($validated['nomor_seri']);
        }

        $skuText = strtoupper($validated['sku']);

        // Resolve (or create) the catalog Sku row by EXACT text match only — no
        // fuzzy/loose matching. This is the one point where a human is actively
        // choosing the product, so it's the right place to settle sku_id once
        // and for all; every later scan just reads it back via FK, no guessing.
        $skuModel = Sku::where('sku', $skuText)->first();
        if (!$skuModel) {
            $skuModel = Sku::create(['sku' => $skuText, 'is_active' => true]);
        }

        $seri = Seri::create([
            'nomor_seri' => $nomorSeri,
            'sku' => $skuText,
            'sku_id' => $skuModel->id,
            'jumlah' => (int) $validated['jumlah'],
            'source' => $validated['source'] ?? 'Form Seri',
        ]);

        return response()->json([
            'message' => 'Seri berhasil dibuat',
            'data' => $seri,
        ], 201);
    }

    public function download($id)
    {
        $seri = Seri::findOrFail($id);
        $jumlahBarcode = max(1, (int) ($seri->jumlah ?? 1));
        $nomorAwalCetak = (int) Seri::where('nomor_seri', $seri->nomor_seri)
            ->where('id', '<', $seri->id)
            ->sum('jumlah');
        $labels = [];

        for ($i = 1; $i <= $jumlahBarcode; $i++) {
            $nomorSeriCetak = $seri->nomor_seri . '.' . ($nomorAwalCetak + $i);
            $qrContent = strtoupper($seri->sku . ' | ' . $nomorSeriCetak);

            $qr = QrCode::format('svg')
                ->size(300)
                ->generate($qrContent);

            $labels[] = [
                'sku' => strtoupper($seri->sku),
                'nomor_seri' => strtoupper($nomorSeriCetak),
                'qr' => base64_encode($qr),
            ];
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.qr_seri', [
            'labels' => $labels,
        ]);

        $pdf->setPaper([0, 0, 141.7, 141.7]);

        return $pdf->download("qr-seri-{$seri->nomor_seri}.pdf");
    }

    private function buildStockAwalNomorSeri(string $sku): string
    {
        $normalizedSku = preg_replace('/[^A-Z0-9]+/', '-', strtoupper(trim($sku)));
        $normalizedSku = trim($normalizedSku ?? '', '-');

        return $normalizedSku !== '' ? 'SA-' . $normalizedSku : 'SA';
    }

    private function buildTanpaSeriNomorSeri(string $sku): string
    {
        $normalizedSku = preg_replace('/[^A-Z0-9]+/', '-', strtoupper(trim($sku)));
        $normalizedSku = trim($normalizedSku ?? '', '-');

        return $normalizedSku !== '' ? 'TS-' . $normalizedSku : 'TS';
    }

    private function buildReturnNomorSeri(string $sku): string
    {
        $normalizedSku = preg_replace('/[^A-Z0-9]+/', '-', strtoupper(trim($sku)));
        $normalizedSku = trim($normalizedSku ?? '', '-');

        return $normalizedSku !== '' ? 'RTN-' . $normalizedSku : 'RTN';
    }

    public function show($id)
    {
        $seri = Seri::findOrFail($id);

        $svg = QrCode::format('svg')->size(300)->generate($seri->nomor_seri);
        $svgBase64 = base64_encode($svg);

        return response()->json([
            'seri' => $seri,
            'qr_svg_base64' => $svgBase64,
        ]);
    }

    public function destroy($id)
    {
        $seri = Seri::findOrFail($id);

        // Barcode per unit ("NOMORSERI.N") dihitung dinamis dari running sum jumlah
        // seluruh baris se-nomor_seri (LINTAS SKU — lihat download()/getSeriScanDetails()/
        // cancelSeriPrint()) yang id-nya lebih kecil. Menghapus baris yang BUKAN baris
        // terakhir dalam grup itu menggeser offset semua baris berikutnya — termasuk
        // baris SKU lain yang berbagi nomor_seri yang sama — sehingga barcode yang sudah
        // dicetak/ditempel secara fisik untuk baris-baris tersebut tidak lagi cocok
        // dengan nomor yang dihitung sistem setelah baris ini dihapus.
        $hasNewerSiblings = Seri::where('nomor_seri', $seri->nomor_seri)
            ->where('id', '>', $seri->id)
            ->exists();

        if ($hasNewerSiblings) {
            return response()->json([
                'message' => 'Baris ini tidak bisa dihapus karena ada baris lain dengan nomor seri yang sama (SKU apa pun) dibuat setelahnya. Menghapusnya akan mengacaukan nomor barcode yang sudah dicetak untuk baris-baris tersebut. Hapus dulu baris yang paling baru (terakhir) di grup ini.',
            ], 422);
        }

        $seri->delete();

        return response()->json([
            'message' => 'Data seri berhasil dihapus.'
        ]);
    }

    public function report(Request $request)
    {
        $dateFrom  = $request->input('date_from');
        $dateTo    = $request->input('date_to');
        $source    = $request->input('source');
        $groupBy   = $request->input('group_by', 'nomor_seri');

        // Default to last 30 days if no date provided
        if (!$dateFrom && !$dateTo) {
            $dateFrom = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
            $dateTo   = \Carbon\Carbon::now()->format('Y-m-d');
        }

        $query = Seri::query();

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($source && $source !== 'all') {
            $query->where('source', $source);
        }

        // Hard limit to prevent timeout
        $allSeris = $query->orderBy('created_at', 'desc')->limit(2000)->get();

        // Gather all unique nomor_seri values for scan lookup
        $uniqueNomorSeris = $allSeris->pluck('nomor_seri')->unique()->all();
        $scannedBarcodesMap = [];

        // Only do scan lookup if manageable number of unique seris
        if (!empty($uniqueNomorSeris) && count($uniqueNomorSeris) <= 200) {
            $activities = \Illuminate\Support\Facades\DB::table('gudang_produk_activity_logs')
                ->where('type', 'placement')
                ->where(function ($q) use ($uniqueNomorSeris) {
                    foreach ($uniqueNomorSeris as $ns) {
                        $q->orWhere('notes', 'like', "%Kode seri: {$ns}.%");
                    }
                })
                ->pluck('notes');

            foreach ($activities as $note) {
                if (preg_match('/Kode seri:\s*([^\s,|]+)/i', $note, $matches)) {
                    $barcodeKey = strtoupper(rtrim(trim($matches[1]), '., '));
                    $scannedBarcodesMap[$barcodeKey] = true;
                }
            }
        }

        // Running sums per nomor_seri (LINTAS SKU — harus sama dengan grouping yang
        // dipakai download()/getSeriScanDetails()/cancelSeriPrint(), agar barcode yang
        // dicek di sini identik dengan barcode yang sebenarnya dicetak/divalidasi saat scan)
        $allSerisForSum = !empty($uniqueNomorSeris)
            ? Seri::whereIn('nomor_seri', $uniqueNomorSeris)->orderBy('id')->get(['id', 'nomor_seri', 'jumlah'])
            : collect();

        $runningSums = [];
        $groupedForSum = $allSerisForSum->groupBy(fn($i) => $i->nomor_seri);
        foreach ($groupedForSum as $key => $items) {
            $sum = 0;
            foreach ($items->sortBy('id') as $item) {
                $runningSums[$item->id] = $sum;
                $sum += (int) $item->jumlah;
            }
        }

        // Enrich each seri row with scan counts
        $enriched = $allSeris->map(function ($item) use ($runningSums, $scannedBarcodesMap) {
            $jumlah  = max(1, (int) $item->jumlah);
            $offset  = $runningSums[$item->id] ?? 0;
            $nsUpper = strtoupper($item->nomor_seri);
            $scanned = 0;

            for ($i = 1; $i <= $jumlah; $i++) {
                $barcode = "{$nsUpper}." . ($offset + $i);
                if (isset($scannedBarcodesMap[$barcode])) {
                    $scanned++;
                }
            }

            return [
                'id'          => $item->id,
                'nomor_seri'  => $item->nomor_seri,
                'sku'         => $item->sku,
                'jumlah'      => $jumlah,
                'source'      => $item->source ?? 'Form Seri',
                'created_at'  => $item->created_at,
                'scanned'     => $scanned,
                'unscanned'   => $jumlah - $scanned,
                'pct'         => $jumlah > 0 ? round(($scanned / $jumlah) * 100, 1) : 0,
            ];
        });

        // Summary stats
        $totalItems   = $enriched->sum('jumlah');
        $totalScanned = $enriched->sum('scanned');
        $totalSeri    = $enriched->count();
        $bySource     = $enriched->groupBy('source')->map(fn($g) => [
            'count'   => $g->count(),
            'jumlah'  => $g->sum('jumlah'),
            'scanned' => $g->sum('scanned'),
        ]);

        // Group rows
        if ($groupBy === 'sku') {
            $grouped = $enriched->groupBy('sku')->map(fn($g, $key) => [
                'group_key' => $key,
                'rows'      => $g->values(),
                'total'     => $g->sum('jumlah'),
                'scanned'   => $g->sum('scanned'),
                'pct'       => $g->sum('jumlah') > 0
                    ? round(($g->sum('scanned') / $g->sum('jumlah')) * 100, 1)
                    : 0,
            ])->values();
        } elseif ($groupBy === 'date') {
            $grouped = $enriched->groupBy(fn($r) => \Carbon\Carbon::parse($r['created_at'])->format('Y-m-d'))
                ->map(fn($g, $key) => [
                    'group_key' => $key,
                    'rows'      => $g->values(),
                    'total'     => $g->sum('jumlah'),
                    'scanned'   => $g->sum('scanned'),
                    'pct'       => $g->sum('jumlah') > 0
                        ? round(($g->sum('scanned') / $g->sum('jumlah')) * 100, 1)
                        : 0,
                ])->values();
        } else {
            // Group by nomor_seri (default)
            $grouped = $enriched->groupBy('nomor_seri')->map(fn($g, $key) => [
                'group_key' => $key,
                'rows'      => $g->values(),
                'total'     => $g->sum('jumlah'),
                'scanned'   => $g->sum('scanned'),
                'pct'       => $g->sum('jumlah') > 0
                    ? round(($g->sum('scanned') / $g->sum('jumlah')) * 100, 1)
                    : 0,
            ])->sortByDesc('group_key')->values();
        }

        return response()->json([
            'summary' => [
                'total_seri'    => $totalSeri,
                'total_barang'  => $totalItems,
                'total_scanned' => $totalScanned,
                'total_unscanned' => $totalItems - $totalScanned,
                'pct_scanned'   => $totalItems > 0 ? round(($totalScanned / $totalItems) * 100, 1) : 0,
                'by_source'     => $bySource,
            ],
            'data' => $grouped,
        ]);
    }
}

