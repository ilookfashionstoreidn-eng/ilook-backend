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
            $seri = Seri::select('id', 'nomor_seri', 'sku', 'jumlah')
                ->orderBy('nomor_seri')
                ->get();

            return response()->json([
                'data' => $seri,
            ]);
        }

        // Default: pagination untuk halaman manajemen
        $seri = Seri::orderBy('created_at', 'desc')->paginate(10);

        // Ubah item dalam paginator (pakai ->getCollection())
        $seri->getCollection()->transform(function ($item) {
            $svg = QrCode::format('svg')->size(200)->generate($item->nomor_seri);
            $item->qr_svg_base64 = base64_encode($svg);
            return $item;
        });

        return response()->json($seri);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nomor_seri' => 'required',
            'sku' => 'required',
            'jumlah' => 'required|integer|min:1',
        ]);

        $seri = Seri::create([
            'nomor_seri' => strtoupper($validated['nomor_seri']),
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
        $labels = [];

        for ($i = 1; $i <= $jumlahBarcode; $i++) {
            $nomorSeriCetak = $seri->nomor_seri . '.' . $i;
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
