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
  $seri = Seri::orderBy('created_at', 'desc')->get();

    $data = $seri->map(function ($item) {
        $svg = QrCode::format('svg')->size(200)->generate($item->nomor_seri);
        $item->qr_svg_base64 = base64_encode($svg);
        return $item;
    });

    return response()->json($data);
}

   public function store(Request $request)
{
    $validated = $request->validate([
        'nomor_seri' => 'required|unique:seri,nomor_seri',
        'sku'        => 'required',
    ]);

    $seri = Seri::create([
        'nomor_seri' => $validated['nomor_seri'],
        'sku'        => $validated['sku'],
    ]);

    return response()->json([
        'message' => 'Seri berhasil dibuat',
        'data' => $seri
    ], 201);
}


    public function download($id)
    {
        $seri = Seri::findOrFail($id);

        // QR nomor seri
        $qrSeri = QrCode::format('svg')->size(300)->generate($seri->nomor_seri);
        $qrSeriBase64 = base64_encode($qrSeri);

        // QR SKU
        $qrSku = QrCode::format('svg')->size(300)->generate($seri->sku);
        $qrSkuBase64 = base64_encode($qrSku);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.qr_seri', [
            'seri' => $seri,
            'qr_seri' => $qrSeriBase64,
            'qr_sku'  => $qrSkuBase64,
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
