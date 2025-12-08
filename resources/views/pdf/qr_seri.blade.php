<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { 
            margin: 0; 
            padding: 0; }

        body {
            margin: 0;
            padding: 0;
            width: 50mm;
            height: 60mm;
            display: flex;
            flex-direction: column;
            justify-content: start;
            align-items: center;
            text-align: center;
            font-family: sans-serif;
            margin-top:2mm;
        }

        .qr-img {
            width: 18mm;
            height: 18mm;
        }

        .kode {
            font-size: 7pt;
            margin-top: 1mm;
            margin-bottom: 1mm;
        }
    </style>
</head>
<body>

    <!-- QR Nomor Seri -->
    <img class="qr-img" src="data:image/svg+xml;base64,{{ $qr_seri }}">
    <div class="kode">{{ $seri->nomor_seri }}</div>

    <!-- QR SKU -->
    <img class="qr-img" src="data:image/svg+xml;base64,{{ $qr_sku }}">
    <div class="kode">{{ $seri->sku }}</div>

</body>
</html>
