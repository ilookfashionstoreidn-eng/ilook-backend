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
            height: 60mm;
            display: flex;
            flex-direction: column;
            justify-content: start;
            align-items: center;
            text-align: center;
            font-family: sans-serif;
            margin-top: 2mm;
        }
        .qr-img {
            width: 35mm;
            height: 35mm;
            margin-top: 3px;
            margin-bottom : 2px;
        }
        .kode {
            font-size: 7pt;
            margin-top: 1mm;
            margin-bottom: 0.5mm;
            line-height: 1.2;
        }
    </style>
</head>
<body>

    <!-- ✅ SATU QR SAJA (isi: SKU + Nomor Seri) -->
    <img class="qr-img" src="data:image/svg+xml;base64,{{ $qr }}">

    <!-- Optional: teks info -->
    <div class="kode">{{ $seri->sku }} | </div>
    <div class="kode">{{ $seri->nomor_seri }}</div>

</body>
</html>
