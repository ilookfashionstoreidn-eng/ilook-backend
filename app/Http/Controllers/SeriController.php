<?php

namespace App\Http\Controllers;

use App\Models\Seri;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SeriController extends Controller
{
    public function index()
    {
        // Mode list penuh untuk dropdown/search
        if (request()->boolean('all')) {
            $query = Seri::select('id', 'nomor_seri', 'sku', 'jumlah', 'created_at');

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
        
        $seri = $query->orderBy('created_at', 'desc')->paginate(10);

        // Fetch scanned barcodes in a single query for all items on the page
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

        // Ubah item dalam paginator (pakai ->getCollection())
        $seri->getCollection()->transform(function ($item) use ($runningSums, $scannedBarcodesMap) {
            // Note: SVG QR code generation is removed because it is not used in the management page index list table,
            // which saves significant CPU time.
            
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
        if (str_contains($search, '|')) {
            $parts = array_map('trim', explode('|', $search, 2));
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

        $seri = Seri::where('nomor_seri', strtoupper(trim($serial)))->first();

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

        $seri = Seri::create([
            'nomor_seri' => $nomorSeri,
            'sku' => strtoupper($validated['sku']),
            'jumlah' => (int) $validated['jumlah'],
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
}
