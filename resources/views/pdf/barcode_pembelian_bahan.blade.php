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

        .nama-bahan {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 2mm;
            color: #000;
        }

        .warna {
            font-size: 10pt;
            color: #333;
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
            <div class="nama-bahan">
                {{ optional(optional($pembelianBahan->bahan))->nama_bahan ?? '-' }} -
                {{ optional($rol->warna)->warna ?? '-' }}
            </div>
        </div>
    @endforeach

</body>

</html>
