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
    $seri = Seri::all();

    $data = $seri->map(function ($item) {
        $svg = QrCode::format('svg')->size(200)->generate($item->nomor_seri);
        $item->qr_svg_base64 = base64_encode($svg);
        return $item;
    });

    return response()->json($data);
}

    public function store(Request $request)
    {
        $request->validate([
            'nomor_seri' => 'required|unique:seri,nomor_seri',
        ]);

        return Seri::create([
            'nomor_seri' => $request->nomor_seri
        ]);
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
 public function download($id)
{
    $seri = Seri::findOrFail($id);

    
    $qrRaw = QrCode::format('svg')
        ->size(200)
        ->generate($seri->nomor_seri);

    
    $qrClean = preg_replace('/<\?xml.*?\?>/i', '', $qrRaw);

    $qrSize = 200;
    $canvas = 300; 
    $offset = ($canvas - $qrSize) / 2;

    
    $textY = $offset + $qrSize + 25; 

    $svg = "
    <svg width='{$canvas}' height='{$canvas}' xmlns='http://www.w3.org/2000/svg'>
        <g transform='translate({$offset}, {$offset})'>
            {$qrClean}
        </g>

        <text 
            x='" . ($canvas / 2) . "' 
            y='{$textY}'
            text-anchor='middle' 
            font-size='20'
            font-family='Arial'
        >
            {$seri->nomor_seri}
        </text>
    </svg>";

    $cleanName = 'qr_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $seri->nomor_seri) . '.svg';

    return response()->stream(function () use ($svg) {
        echo $svg;
    }, 200, [
        "Content-Type" => "image/svg+xml",
        "Content-Disposition" => "attachment; filename=\"{$cleanName}\"",
    ]);
}

}
