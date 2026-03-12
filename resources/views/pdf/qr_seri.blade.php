<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
            size: 50mm 50mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 50mm;
            font-family: sans-serif;
        }

        .page {
            position: relative;
            width: 50mm;
            height: 50mm;
            overflow: hidden;
            page-break-after: always;
            page-break-inside: avoid;
            text-align: center;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .qr-img {
            width: 35mm;
            height: 35mm;
            margin-top: 2mm;
            margin-bottom: 1mm;
        }

        .kode {
            font-size: 7pt;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    @foreach ($labels as $label)
        <div class="page">
            <img class="qr-img" src="data:image/svg+xml;base64,{{ $label['qr'] }}">
            <p class="kode">{{ $label['sku'] }} |</p>
            <p class="kode">{{ $label['nomor_seri'] }}</p>
        </div>
    @endforeach
</body>
</html>
