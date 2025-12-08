<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            margin-top: 5mm;
            width: 50mm;
            height: 50mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .qr-code {
            margin: 0;
        }

        .qr-code img {
            width: 35mm;
            height: 35mm;
        }

        .barcode-text {
            font-size: 8pt;
            font-weight: normal;
            margin-top: 2mm;
            color: #000;
            text-transform: lowercase;
        }
    </style>
</head>

<body>
    @php
        $dns2d = new \Milon\Barcode\DNS2D();
        $qrBase64 = $dns2d->getBarcodePNG($spkCutting->barcode, 'QRCODE', 6, 6);
    @endphp
    <div class="qr-code">
        <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR Code">
    </div>
    <div class="barcode-text">
        {{ $spkCutting->id_spk_cutting }}
    </div>
</body>

</html>
