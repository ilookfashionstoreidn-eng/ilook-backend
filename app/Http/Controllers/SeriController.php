<?php

namespace App\Http\Controllers;

use App\Models\Seri;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

class SeriController extends Controller
{
  public function index()
{
    // Mode list penuh untuk dropdown/search
    if (request()->boolean('all')) {
        $seri = Seri::select('id', 'nomor_seri', 'sku')
            ->orderBy('nomor_seri')
            ->get();

        return response()->json([
            'data' => $seri
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
        'sku'        => 'required',
    ]);

    $baseNomorSeri = $validated['nomor_seri'];
    $sku = $validated['sku'];

    $existingSeris = Seri::where(function($query) use ($baseNomorSeri) {
            $query->where('nomor_seri', $baseNomorSeri)
                  ->orWhere('nomor_seri', 'LIKE', $baseNomorSeri . '.%');
        })
        ->get();

    if ($existingSeris->isEmpty()) {
        $finalNomorSeri = $baseNomorSeri;
    } else {
        $maxSeq = 0;
        foreach ($existingSeris as $item) {
            if ($item->nomor_seri !== $baseNomorSeri) {
                $prefixLen = strlen($baseNomorSeri) + 1; // +1 untuk titik
                $seqStr = substr($item->nomor_seri, $prefixLen);
                if (is_numeric($seqStr)) {
                    $seq = (int) $seqStr;
                    if ($seq > $maxSeq) {
                        $maxSeq = $seq;
                    }
                }
            }
        }
        $nextSeq = $maxSeq + 1;
        $finalNomorSeri = $baseNomorSeri . '.' . $nextSeq;
    }

    $seri = Seri::create([
        'nomor_seri' => $finalNomorSeri,
        'sku'        => $sku,
    ]);

    return response()->json([
        'message' => 'Seri berhasil dibuat',
        'data' => $seri
    ], 201);
}


   public function download($id)
{
    $seri = Seri::findOrFail($id);

    // 🔥 FORMAT STRING QR (SKU | NOMOR SERI)
    $qrContent = strtoupper(
        $seri->sku . ' | ' . $seri->nomor_seri
    );

    // Generate QR
    $qr = QrCode::format('svg')
        ->size(300)
        ->generate($qrContent);

    $qrBase64 = base64_encode($qr);

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.qr_seri', [
        'seri' => $seri,
        'qr'   => $qrBase64,
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
            'qr_svg_base64' => $svgBase64
        ]);
    }


}
