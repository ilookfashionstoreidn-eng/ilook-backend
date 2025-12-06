<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial;
            padding: 20px;
        }

        .test-marker {
            background: yellow;
            font-size: 24pt;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="test-marker">
        *** INI FILE BLADE BARU - TEST BERHASIL! ***
    </div>

    <h2>Data Pembelian Bahan:</h2>
    <p><strong>ID:</strong> {{ $pembelianBahan->id ?? 'N/A' }}</p>
    <p><strong>Pabrik:</strong> {{ optional($pembelianBahan->pabrik)->nama_pabrik ?? 'TIDAK ADA DATA PABRIK' }}</p>
    <p><strong>Bahan:</strong> {{ optional($pembelianBahan->bahan)->nama_bahan ?? 'TIDAK ADA DATA BAHAN' }}</p>
    <p><strong>Tanggal Kirim:</strong> {{ $pembelianBahan->tanggal_kirim ?? 'N/A' }}</p>
    <p><strong>Gramasi:</strong> {{ $pembelianBahan->gramasi ?? 'N/A' }}</p>
    <p><strong>Lebar Kain:</strong> {{ $pembelianBahan->lebar_kain ?? 'N/A' }}</p>

    <h2>Barcodes:</h2>
    @foreach ($barcodes as $index => $rol)
        <div style="border: 2px solid red; padding: 10px; margin: 10px 0;">
            <p><strong>Barcode {{ $index + 1 }}:</strong> {{ $rol->barcode }}</p>
            <p><strong>Warna:</strong> {{ optional($rol->warna)->warna ?? 'TIDAK ADA WARNA' }}</p>
            <p><strong>Berat:</strong> {{ $rol->berat ?? 'TIDAK ADA BERAT' }} kg</p>

            @php
                $dns2d = new \Milon\Barcode\DNS2D();
                $qrBase64 = $dns2d->getBarcodePNG($rol->barcode, 'QRCODE', 4, 4);
            @endphp
            <img src="data:image/png;base64,{{ $qrBase64 }}" style="width: 100px; height: 100px;">
        </div>
    @endforeach
</body>

</html>
