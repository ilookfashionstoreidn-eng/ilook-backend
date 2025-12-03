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
            margin: 0;
            padding: 0;
            width: 50mm;
            height: 50mm;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            margin-top: 19px;
        }

        .qr-img {
            width: 38mm;
            height: 35mm;
        }

        .kode {
            font-size: 8pt;
            margin-top: 8mm;
        }
    </style>
</head>

<body>

    @foreach ($barcodes as $rol)
        <div class="page">
            @php
                $dns2d = new \Milon\Barcode\DNS2D();
                $qrBase64 = $dns2d->getBarcodePNG($rol->barcode, 'QRCODE', 6, 6);
            @endphp
            <img class="qr-img" src="data:image/png;base64,{{ $qrBase64 }}">
            <div class="nama">{{ optional($pembelianBahan->bahan)->nama_bahan }}</div>

        </div>
    @endforeach

</body>

</html>
