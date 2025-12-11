<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
            padding: 0;
            size: 105mm 148.5mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .page-container {
            width: 105mm;
            height: 148.5mm;
            padding: 3mm;
            background: #ffffff;
        }

        /* Header Section */
        .header {
            border: 2px solid #17457c;
            background: linear-gradient(135deg, #17457c 0%, #1e5a9e 100%);
            color: #ffffff;
            padding: 3mm;
            text-align: center;
            margin-bottom: 2mm;
            border-radius: 2px;
        }

        .company-name {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 1mm;
            letter-spacing: 0.5px;
        }

        .document-type {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            padding: 1mm 0;
            margin: 1mm 0;
        }

        .spk-number {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 1mm;
        }

        /* Main Content */
        .content-section {
            border: 1px solid #d0d0d0;
            padding: 2mm;
            margin-bottom: 2mm;
            background: #fafafa;
            border-radius: 2px;
        }

        .section-title {
            font-size: 7pt;
            font-weight: bold;
            color: #17457c;
            margin-bottom: 1.5mm;
            text-transform: uppercase;
            border-bottom: 1px solid #17457c;
            padding-bottom: 0.5mm;
        }

        .info-grid {
            display: table;
            width: 100%;
            font-size: 6.5pt;
        }

        .info-row {
            display: table-row;
            margin-bottom: 0.5mm;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            color: #333;
            width: 35%;
            padding: 0.5mm 0;
            vertical-align: top;
        }

        .info-separator {
            display: table-cell;
            width: 3%;
            padding: 0 1mm;
            text-align: center;
            color: #666;
        }

        .info-value {
            display: table-cell;
            color: #000;
            padding: 0.5mm 0;
            vertical-align: top;
        }

        /* Top Section - Barcode dan Foto Produk Sejajar */
        .top-section {
            display: table;
            width: 100%;
            margin-bottom: 2mm;
        }

        .barcode-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 1mm;
        }

        .product-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-left: 1mm;
        }

        /* QR Code Section */
        .qr-section {
            text-align: center;
            padding: 1.5mm;
            background: #ffffff;
            border: 1px solid #d0d0d0;
            border-radius: 2px;
        }

        .qr-code {
            display: inline-block;
            padding: 1mm;
            background: #ffffff;
            border: 1px solid #d0d0d0;
        }

        .qr-code img {
            width: 25mm;
            height: 25mm;
            display: block;
        }

        .barcode-text {
            font-size: 5.5pt;
            color: #333;
            margin-top: 0.8mm;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }

        /* Product Image */
        .product-image-container {
            text-align: center;
            padding: 1mm;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 2px;
        }

        .product-image {
            max-width: 100%;
            max-height: 30mm;
            object-fit: contain;
        }

        /* Detail Bahan Table */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
            margin-top: 1mm;
        }

        .detail-table th {
            background: #17457c;
            color: #ffffff;
            padding: 1mm;
            text-align: left;
            font-weight: bold;
            font-size: 6pt;
            border: 1px solid #17457c;
        }

        .detail-table td {
            padding: 0.8mm;
            border: 1px solid #d0d0d0;
            background: #ffffff;
        }

        .detail-table tr:nth-child(even) td {
            background: #f9f9f9;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.5mm 2mm;
            border-radius: 3px;
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-in-progress {
            background: #ffc107;
            color: #000;
        }

        .status-pending {
            background: #6c757d;
            color: #fff;
        }

        .status-completed {
            background: #28a745;
            color: #fff;
        }

        /* Footer */
        .footer {
            margin-top: 2mm;
            padding-top: 1mm;
            border-top: 1px solid #d0d0d0;
            text-align: center;
            font-size: 5pt;
            color: #666;
        }

        .footer-text {
            margin: 0.3mm 0;
        }
    </style>
</head>

<body>
    @php
        $dns2d = new \Milon\Barcode\DNS2D();
        // Pastikan semua relasi sudah di-load
        if (!$spkCutting->relationLoaded('produk')) {
            $spkCutting->load('produk');
        }
        if (!$spkCutting->relationLoaded('tukangCutting')) {
            $spkCutting->load('tukangCutting');
        }
        if (!$spkCutting->relationLoaded('bagian')) {
            $spkCutting->load('bagian.bahan.bahan');
        }

        // Siapkan path foto produk untuk PDF
        $fotoProdukPath = null;
        $fotoProdukBase64 = null;
        if ($spkCutting->produk && $spkCutting->produk->gambar_produk) {
            $possiblePaths = [
                public_path('storage/' . $spkCutting->produk->gambar_produk),
                storage_path('app/public/' . $spkCutting->produk->gambar_produk),
                storage_path('app/' . $spkCutting->produk->gambar_produk),
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $fotoProdukPath = $path;
                    $imageData = file_get_contents($path);
                    $imageInfo = getimagesize($path);
                    $mimeType = $imageInfo ? $imageInfo['mime'] : 'image/jpeg';
                    $fotoProdukBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    break;
                }
            }

            if (!$fotoProdukPath) {
                $fotoProdukPath = asset('storage/' . $spkCutting->produk->gambar_produk);
            }
        }

    @endphp

    <div class="page-container">
        <!-- Header -->


        <!-- Barcode dan Foto Produk Sejajar di Atas -->
        <div class="top-section">
            <!-- Barcode di Kiri -->
            <div class="barcode-left">
                <div class="qr-section">
                    <div class="section-title" style="margin-bottom: 1mm; font-size: 6pt;">Barcode</div>
                    @php
                        $qrBase64 = $dns2d->getBarcodePNG($spkCutting->barcode, 'QRCODE', 6, 6);
                    @endphp
                    <div class="qr-code">
                        <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR Code">
                    </div>
                    <div class="barcode-text">{{ $spkCutting->barcode }}</div>
                </div>
            </div>

            <!-- Foto Produk di Kanan -->
            <div class="product-right">
                @if ($fotoProdukBase64)
                    <div class="content-section" style="margin-bottom: 0;">
                        <div class="section-title" style="font-size: 6pt;">Foto Produk</div>
                        <div class="product-image-container">
                            <img src="{{ $fotoProdukBase64 }}" alt="Foto Produk" class="product-image">
                        </div>
                    </div>
                @elseif ($spkCutting->produk && $spkCutting->produk->gambar_produk)
                    <div class="content-section" style="margin-bottom: 0;">
                        <div class="section-title" style="font-size: 6pt;">Foto Produk</div>
                        <div class="product-image-container">
                            <img src="{{ asset('storage/' . $spkCutting->produk->gambar_produk) }}" alt="Foto Produk"
                                class="product-image">
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Informasi SPK -->
        <div class="content-section">
            <div class="section-title">Informasi SPK</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Produk</div>
                    <div class="info-separator">:</div>
                    <div class="info-value">{{ $spkCutting->produk->nama_produk ?? '-' }}</div>
                </div>
                @if ($spkCutting->tukangCutting)
                    <div class="info-row">
                        <div class="info-label">Tukang Cutting</div>
                        <div class="info-separator">:</div>
                        <div class="info-value">{{ $spkCutting->tukangCutting->nama_tukang_cutting }}</div>
                    </div>
                @endif
                @if ($spkCutting->tanggal_batas_kirim)
                    <div class="info-row">
                        <div class="info-label">Batas Kirim</div>
                        <div class="info-separator">:</div>
                        <div class="info-value">
                            {{ \Carbon\Carbon::parse($spkCutting->tanggal_batas_kirim)->format('d F Y') }}</div>
                    </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Nomor Seri</div>
                    <div class="info-separator">:</div>
                    <div class="info-value">
                        <strong>{{ $spkCutting->id_spk_cutting }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Bahan -->
        @if ($spkCutting->bagian && $spkCutting->bagian->count() > 0)
            <div class="content-section">
                <div class="section-title">Detail Bahan</div>
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Bagian</th>
                            <th style="width: 30%;">Bahan</th>
                            <th style="width: 15%;">Qty</th>
                            <th style="width: 20%;">Warna</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($spkCutting->bagian->take(3) as $bagian)
                            @foreach ($bagian->bahan->take(2) as $bahan)
                                <tr>
                                    <td>{{ $bagian->nama_bagian }}</td>
                                    <td>{{ $bahan->bahan->nama_bahan ?? '-' }}</td>
                                    <td>{{ $bahan->qty }} rol</td>
                                    <td>{{ $bahan->warna ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        @if (
                            $spkCutting->bagian->count() > 3 ||
                                $spkCutting->bagian->sum(function ($b) {
                                    return $b->bahan->count();
                                }) > 6)
                            <tr>
                                <td colspan="4"
                                    style="text-align: center; font-style: italic; color: #666; padding: 1mm;">
                                    ... dan lainnya
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif


      
    </div>
</body>

</html>
