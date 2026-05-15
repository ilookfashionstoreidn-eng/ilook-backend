<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
        }

        .sheet {
            width: 100%;
            page-break-inside: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .main-grid,
        .photo-grid,
        .cut-table {
            border: 1.6px solid #34475f;
        }

        .main-grid td,
        .main-grid th,
        .photo-grid td,
        .cut-table td,
        .cut-table th {
            border: 1.3px solid #34475f;
        }

        .main-left {
            width: 70%;
            height: 76mm;
            padding: 0;
            vertical-align: top;
        }

        .main-right {
            width: 30%;
            padding: 0;
            vertical-align: top;
        }

        .print-header {
            height: 23mm;
            padding: 3mm 4mm 1.5mm;
            border-bottom: 1.3px solid #34475f;
        }

        .qr-box {
            float: left;
            width: 20mm;
            height: 20mm;
            text-align: center;
        }

        .qr-box img {
            width: 17mm;
            height: 17mm;
        }

        .qr-code-text {
            font-size: 4.7pt;
            line-height: 1.1;
            word-break: break-all;
        }

        .brand-box {
            margin-left: 24mm;
            padding-top: 2.2mm;
            text-align: center;
            border-top: 1.2px dashed #34475f;
            border-bottom: 1.2px dashed #34475f;
            min-height: 17mm;
        }

        .brand-name {
            font-size: 11pt;
            font-weight: 700;
            margin-bottom: 1.2mm;
        }

        .brand-city,
        .print-time {
            font-size: 10pt;
            line-height: 1.2;
        }

        .material-list {
            padding: 3mm 3mm 1mm;
            min-height: 42mm;
        }

        .material-row {
            width: 100%;
            border-bottom: 1px dashed #d8dee8;
            font-size: 9pt;
            line-height: 1.2;
            margin-bottom: 1mm;
            padding-bottom: 0.6mm;
        }

        .material-name {
            display: inline-block;
            width: 91%;
        }

        .material-qty {
            display: inline-block;
            width: 7%;
            text-align: right;
        }

        .total-row {
            margin: 1.5mm 3mm 0;
            border-top: 1.6px solid #34475f;
            background: #f5f7fb;
            height: 9mm;
            font-size: 10pt;
            line-height: 9mm;
            text-align: center;
        }

        .total-number {
            float: right;
            margin-right: 3mm;
            color: #3f37c9;
            font-size: 13pt;
            font-weight: 700;
        }

        .product-title {
            height: 10mm;
            text-align: center;
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: 0.2px;
            vertical-align: middle;
            background: #fbfcff;
        }

        .series-cell {
            height: 9mm;
            text-align: center;
            vertical-align: middle;
            font-size: 8.5pt;
        }

        .meta-cell {
            height: 9mm;
            text-align: center;
            vertical-align: middle;
            font-size: 8pt;
        }

        .deadline-cell {
            height: 14mm;
            text-align: center;
            vertical-align: middle;
            background: #fff5f5;
        }

        .deadline-label {
            color: #dc2626;
            font-size: 6.8pt;
            font-weight: 700;
        }

        .deadline-value {
            color: #dc2626;
            font-size: 9.5pt;
            font-weight: 800;
            margin-top: 0.7mm;
        }

        .spec-box {
            height: 34mm;
            padding: 2.4mm 2mm;
            vertical-align: top;
        }

        .spec-title {
            color: #475569;
            font-size: 7pt;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1.4mm;
            padding-bottom: 0.6mm;
            text-transform: uppercase;
        }

        .spec-row {
            font-size: 7.7pt;
            line-height: 1.36;
        }

        .photo-grid {
            margin-top: 4mm;
        }

        .photo-cell {
            width: 20%;
            height: 34mm;
            text-align: center;
            vertical-align: middle;
            background: #f8fafc;
        }

        .photo-cell img {
            max-width: 100%;
            max-height: 33mm;
        }

        .photo-label {
            height: 5mm;
            text-align: center;
            vertical-align: middle;
            font-size: 8pt;
            background: #f8fafc;
            text-transform: uppercase;
        }

        .size-banner {
            margin-top: 5mm;
            border: 1.6px solid #34475f;
            min-height: 11mm;
            padding: 3.2mm 4mm 3.2mm;
            color: #2563eb;
            font-size: 10.5pt;
            font-weight: 800;
        }

        .size-banner-line {
            border-bottom: 1px solid #dbe4ef;
            padding-bottom: 0.5mm;
        }

        .keterangan-text {
            color: #475569;
            font-size: 8pt;
            font-weight: 400;
            margin-top: 1.5mm;
            white-space: pre-line;
        }

        .cut-table {
            margin-top: 5mm;
            font-size: 8pt;
        }

        .cut-table th {
            height: 8mm;
            text-align: center;
            vertical-align: middle;
            background: #f8fafc;
            font-weight: 400;
        }

        .cut-table td {
            height: 5.1mm;
            vertical-align: middle;
        }

        .no-col {
            width: 4%;
            text-align: center;
        }

        .warna-col {
            width: 22%;
            padding-left: 1mm;
        }

        .pcs-col {
            width: 14%;
            text-align: center;
        }

        .number-col {
            width: 6%;
            text-align: center;
        }

        .cumulative {
            height: 7mm;
            text-align: center;
            font-size: 10pt;
        }

        .placeholder {
            color: #94a3b8;
            font-size: 7.5pt;
        }
    </style>
</head>

<body>
    @php
        $dns2d = new \Milon\Barcode\DNS2D();
        $qrBase64 = $dns2d->getBarcodePNG($spkCutting->barcode, 'QRCODE', 6, 6);
        $printedAt = now('Asia/Jakarta');
        $produk = $spkCutting->produk;
        $skus = $spkCutting->skus ?? collect();
        $assignedVariants = collect($assignedVariants ?? []);
        $productTitle = strtoupper(($produk?->product_group ?: ($produk?->nama_produk ?? '-')));
        $picName = strtoupper($spkCutting->pic ?: '-');
        $polaName = strtoupper($spkCutting->tukangPola->nama ?? '-');
        $sizes = $skus->pluck('ukuran')->filter()->unique()->values();
        $colors = $assignedVariants
            ->pluck('warna')
            ->map(fn($warna) => trim((string) $warna))
            ->filter()
            ->unique()
            ->values();
        if ($colors->isEmpty()) {
            $colors = $skus->pluck('warna')->filter()->unique()->values();
        }
        $sizeText = $sizes->count() ? $sizes->implode('/') : '-';
        $sizeCount = max(1, $sizes->count());
        $deadline = $spkCutting->tanggal_batas_kirim
            ? \Carbon\Carbon::parse($spkCutting->tanggal_batas_kirim)->locale('id')->translatedFormat('l, j F')
            : '-';

        $materialRows = collect();
        foreach ($spkCutting->bagian ?? [] as $bagian) {
            foreach ($bagian->bahan ?? [] as $bahan) {
                $materialRows->push([
                    'nama' => trim(($bahan->bahan->nama_bahan ?? '-') . ($bahan->warna ? ' - ' . $bahan->warna : '')),
                    'warna' => $bahan->warna ?: '-',
                    'qty' => (float) ($bahan->qty ?? 0),
                ]);
            }
        }
        $totalQty = $materialRows->sum('qty');
        $tableColors = $materialRows->pluck('warna')->filter(fn($warna) => $warna && $warna !== '-')->unique()->values();
        if ($tableColors->isEmpty()) {
            $tableColors = $colors->isNotEmpty() ? $colors : collect(['']);
        }

        $resolveLocalImageSrc = function ($rawPath) {
            if (!$rawPath) {
                return null;
            }

            $relativeImage = preg_replace('#^https?://[^/]+/storage/#', '', (string) $rawPath);
            $relativeImage = ltrim(str_replace('storage/', '', $relativeImage), '/');
            $possiblePaths = [
                public_path('storage/' . $relativeImage),
                storage_path('app/public/' . $relativeImage),
                storage_path('app/' . $relativeImage),
            ];

            foreach ($possiblePaths as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }

            return null;
        };

        $defaultImageSrc = $resolveLocalImageSrc($produk?->gambar_produk);

        $photoItems = $assignedVariants
            ->map(function ($variant) use ($resolveLocalImageSrc, $defaultImageSrc) {
                return [
                    'label' => trim((string) ($variant['warna'] ?? '')),
                    'image' => $resolveLocalImageSrc($variant['image_path'] ?? null) ?: $defaultImageSrc,
                ];
            })
            ->filter(fn($item) => $item['label'] !== '')
            ->take(5)
            ->values();

        if ($photoItems->isEmpty()) {
            $photoItems = $colors
                ->map(fn($label) => [
                    'label' => trim((string) $label),
                    'image' => $defaultImageSrc,
                ])
                ->values();
        }
    @endphp

    <div class="sheet">
        <table class="main-grid">
            <tr>
                <td class="main-left">
                    <div class="print-header">
                        <div class="qr-box">
                            <img src="data:image/png;base64,{{ $qrBase64 }}" alt="Barcode">
                            <div class="qr-code-text">{{ $spkCutting->barcode }}</div>
                        </div>
                        <div class="brand-box">
                            <div class="brand-name">iLook</div>
                            <div class="brand-city">jakarta</div>
                            <div class="print-time">{{ $printedAt->format('d/m/Y H:i:s') }}</div>
                        </div>
                    </div>

                    <div class="material-list">
                        @foreach ($materialRows->take(8) as $index => $row)
                            <div class="material-row">
                                <span class="material-name">{{ $index + 1 }}. {{ strtoupper($row['nama']) }}</span>
                                <span class="material-qty">{{ rtrim(rtrim(number_format($row['qty'], 2, ',', '.'), '0'), ',') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="total-row">
                        Total
                        <span class="total-number">{{ rtrim(rtrim(number_format($totalQty, 2, ',', '.'), '0'), ',') }}</span>
                    </div>
                </td>
                <td class="main-right">
                    <table>
                        <tr>
                            <td colspan="4" class="product-title">{{ $productTitle }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="series-cell">SERI</td>
                            <td colspan="2" class="series-cell">{{ $spkCutting->id_spk_cutting }}</td>
                        </tr>
                        <tr>
                            <td class="meta-cell">PIC</td>
                            <td class="meta-cell">{{ $picName }}</td>
                            <td class="meta-cell"> Ukuran </td>
                            <td class="meta-cell">{{ $sizeText }}</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="deadline-cell">
                                <div class="deadline-label">BATAS KIRIM</div>
                                <div class="deadline-value">{{ strtoupper($deadline) }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" class="spec-box">
                                <div class="spec-title">SPESIFIKASI TEKNIS</div>
                                <div class="spec-row">LD S: {{ $produk?->ld_s ? $produk->ld_s . ' CM' : '-' }}</div>
                                <div class="spec-row">LD M: {{ $produk?->ld_m ? $produk->ld_m . ' CM' : '-' }}</div>
                                <div class="spec-row">LD L: {{ $produk?->ld_l ? $produk->ld_l . ' CM' : '-' }}</div>
                                <div class="spec-row">LD XL: {{ $produk?->ld_xl ? $produk->ld_xl . ' CM' : '-' }}</div>
                                <div class="spec-row">PJ DRESS: {{ $produk?->pj_dress ? $produk->pj_dress . ' CM' : '-' }}</div>
                                <div class="spec-row">PJ CELANA: {{ $produk?->pj_celana ?: '-' }}</div>
                                <div class="spec-row">PJ BAJU: {{ $produk?->pj_baju ? $produk->pj_baju . ' CM' : '-' }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="photo-grid">
            <tr>
                @foreach ($photoItems as $photoItem)
                    <td class="photo-cell">
                        @if ($photoItem['image'])
                            <img src="{{ $photoItem['image'] }}" alt="{{ $photoItem['label'] }}">
                        @else
                            <span class="placeholder">FOTO PRODUK</span>
                        @endif
                    </td>
                @endforeach
            </tr>
            <tr>
                @foreach ($photoItems as $photoItem)
                    <td class="photo-label">{{ $photoItem['label'] ?: '-' }}</td>
                @endforeach
            </tr>
        </table>

        <div class="size-banner">
            <div class="size-banner-line">BAGI {{ $sizeCount }} SIZE</div>
            @if(!empty($spkCutting->keterangan))
                <div class="keterangan-text">
                    @foreach(explode(',', $spkCutting->keterangan) as $ket)
                        @if(trim($ket) !== '')
                            {{ trim($ket) }}{{ !$loop->last ? ',' : '' }}<br>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <table class="cut-table">
            <thead>
                <tr>
                    <th class="no-col">NO</th>
                    <th class="warna-col">Warna</th>
                    @for ($i = 1; $i <= 10; $i++)
                        <th class="number-col">{{ $i }}</th>
                    @endfor
                    <th class="pcs-col">HASIL POTONG /<br>PCS</th>
                </tr>
            </thead>
            <tbody>
                @for ($row = 1; $row <= 8; $row++)
                    @php
                        $warna = $tableColors->get($row - 1, '');
                    @endphp
                    <tr>
                        <td class="no-col">{{ $row }}</td>
                        <td class="warna-col">{{ strtoupper($warna) }}</td>
                        @for ($i = 1; $i <= 10; $i++)
                            <td class="number-col">&nbsp;</td>
                        @endfor
                        <td class="pcs-col">&nbsp;</td>
                    </tr>
                @endfor
                <tr>
                    <td colspan="2" class="cumulative">CUMULATIVE TOTAL</td>
                    @for ($i = 1; $i <= 11; $i++)
                        <td>&nbsp;</td>
                    @endfor
                </tr>
            </tbody>
        </table>
    </div>
</body>

</html>
