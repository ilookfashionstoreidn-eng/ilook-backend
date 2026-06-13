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
            height: 55mm;
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
            padding: 2.4mm 2.2mm 1mm;
            min-height: 58mm;
        }

        .material-table-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.7mm;
        }

        .main-grid .material-table-grid th,
        .main-grid .material-table-grid td {
            border: 0.8px solid #34475f;
            padding: 1mm 0.8mm;
        }

        .material-table-grid th {
            background: #eef2f7;
            color: #334155;
            font-size: 6.6pt;
            font-weight: 700;
            line-height: 1.15;
            text-align: center;
        }

        .material-table-grid .bagian-title {
            background: #dfe7f0;
            color: #111827;
            font-size: 7.2pt;
            text-transform: uppercase;
        }

        .material-table-grid td {
            font-size: 6.6pt;
            line-height: 1.15;
        }

        .material-table-grid .nama-bahan-cell {
            width: 18%;
        }

        .material-table-grid .warna-cell {
            width: 10%;
            text-align: center;
        }

        .material-table-grid .qty-cell {
            width: 5%;
            text-align: center;
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
            height: 8mm;
            text-align: center;
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: 0.2px;
            vertical-align: middle;
            background: #fbfcff;
        }

        .series-cell {
            height: 7mm;
            text-align: center;
            vertical-align: middle;
            font-size: 8.5pt;
        }

        .sku-cell {
            height: 8mm;
            padding: 1mm 1.5mm;
            text-align: center;
            vertical-align: middle;
            font-size: 6.8pt;
            line-height: 1.2;
        }

        .meta-cell {
            height: 7mm;
            text-align: center;
            vertical-align: middle;
            font-size: 8pt;
        }

        .deadline-cell {
            height: 10mm;
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
            height: 60mm;
            text-align: center;
            vertical-align: middle;
            background: #f8fafc;
        }

        .photo-cell img {
            max-width: 100%;
            max-height: 59mm;
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
            min-height: 16mm;
            padding: 0;
            color: #2563eb;
        }

        .size-info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .size-info-table td {
            width: 50%;
            padding: 3mm 4mm;
            vertical-align: top;
        }

        .size-info-table td + td {
            border-left: 1.3px solid #34475f;
        }

        .size-banner-line {
            border-bottom: 1px solid #dbe4ef;
            padding-bottom: 0.5mm;
            color: #2563eb;
            font-size: 10.5pt;
            font-weight: 800;
        }

        .size-spec-title {
            border-bottom: 1px solid #dbe4ef;
            padding-bottom: 0.5mm;
            color: #475569;
            font-size: 7pt;
            font-weight: 400;
            text-transform: uppercase;
        }

        .size-spec-grid {
            margin-top: 1.2mm;
            color: #111827;
            font-size: 7.7pt;
            font-weight: 400;
            line-height: 1.28;
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
        $productList = $spkCutting->productList;
        $legacyProduk = $spkCutting->produk;
        $produk = $productList ?: $legacyProduk;
        $productListSkus = $spkCutting->productListSkus ?? collect();
        $legacySkus = $spkCutting->skus ?? collect();
        $skus = $productListSkus->isNotEmpty() ? $productListSkus : $legacySkus;
        $assignedVariants = collect($assignedVariants ?? []);
        $productTitle = strtoupper($productList?->product_group ?: ($productList?->product ?: ($legacyProduk?->nama_produk ?? '-')));
        $picName = strtoupper($spkCutting->pic ?: '-');
        $polaName = strtoupper($spkCutting->tukangPola->nama ?? '-');
        $sizes = $skus
            ->map(fn($sku) => trim((string) ($sku->product_size ?? $sku->ukuran ?? '')))
            ->filter()
            ->unique()
            ->values();
        $skuText = $skus
            ->map(fn($sku) => trim((string) ($sku->sku_name ?? $sku->sku ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->implode(', ') ?: '-';
        $colors = $assignedVariants
            ->pluck('warna')
            ->map(fn($warna) => trim((string) $warna))
            ->filter()
            ->unique()
            ->values();
        if ($colors->isEmpty()) {
            $colors = $skus
                ->map(fn($sku) => trim((string) ($sku->product_colour ?? $sku->warna ?? '')))
                ->filter()
                ->unique()
                ->values();
        }
        $sizeText = $sizes->count() ? $sizes->implode('/') : '-';
        $sizeCount = max(1, $sizes->count());
        $deadline = $spkCutting->tanggal_batas_kirim
            ? \Carbon\Carbon::parse($spkCutting->tanggal_batas_kirim)->locale('id')->translatedFormat('l, j F')
            : '-';

        $materialRows = collect();
        $mainMaterialRows = collect();
        $combinationMaterialRows = collect();
        $accessoryMaterialRows = collect();
        foreach ($spkCutting->bagian ?? [] as $bagian) {
            $bagianName = strtoupper(trim((string) ($bagian->nama_bagian ?? '')));
            $isCombination = str_contains($bagianName, 'COMBIN') || str_contains($bagianName, 'KOMBIN');
            $isAccessory = str_contains($bagianName, 'AKSESOR') || str_contains($bagianName, 'ACCESSOR');

            foreach ($bagian->bahan ?? [] as $bahan) {
                $row = [
                    'nama' => $isAccessory ? ($bahan->aksesoris->nama_aksesoris ?? '-') : ($bahan->bahan->nama_bahan ?? '-'),
                    'warna' => $bahan->warna ?: '-',
                    'qty' => (float) ($bahan->qty ?? 0),
                ];

                if ($isAccessory) {
                    $accessoryMaterialRows->push($row);
                } elseif ($isCombination) {
                    $combinationMaterialRows->push($row);
                } else {
                    $mainMaterialRows->push($row);
                }

                $materialRows->push($row);
            }
        }

        $accessoryRows = collect($legacyProduk?->komponen ?? [])
            ->filter(fn($komponen) => ($komponen->sumber_komponen ?? null) === 'aksesoris' && $komponen->aksesoris)
            ->map(fn($komponen) => [
                'nama' => $komponen->aksesoris->nama_aksesoris ?? '-',
                'warna' => '-',
                'qty' => (float) ($komponen->jumlah_bahan ?? 0),
            ])
            ->concat($accessoryMaterialRows)
            ->values();

        $bagianTables = collect([
            [
                'nama_bagian' => 'BAHAN UTAMA',
                'rows' => $mainMaterialRows->values(),
            ],
            [
                'nama_bagian' => 'COMBINASI',
                'rows' => $combinationMaterialRows->values(),
            ],
        ])->filter(fn($table) => $table['rows']->isNotEmpty())->values();

        if ($accessoryRows->isNotEmpty()) {
            $bagianTables->push([
                'nama_bagian' => 'AKSESORIS',
                'rows' => $accessoryRows,
            ]);
        }

        $selectedSkus = collect();
        if (($spkCutting->mode ?? 'biasa') === 'potong_kecil') {
            foreach ($spkCutting->bagian ?? [] as $bagian) {
                foreach ($bagian->bahan ?? [] as $bahan) {
                    foreach ($bahan->skus ?? [] as $sku) {
                        $selectedSkus->push([
                            'sku_name' => $sku->sku_name ?? $sku->sku ?? '-',
                            'warna' => $sku->product_colour ?? $sku->warna ?? $bahan->warna ?? '-',
                            'qty' => (float) ($sku->pivot->qty ?? 0),
                        ]);
                    }
                }
            }
            $totalQty = $selectedSkus->sum('qty');
        } else {
            $totalQty = $materialRows->sum('qty');
        }
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
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    return 'data:image/' . $type . ';base64,' . base64_encode($data);
                }
            }

            return null;
        };

        $defaultImageSrc = $resolveLocalImageSrc($productList?->productListImage?->image_path)
            ?: $resolveLocalImageSrc($legacyProduk?->gambar_produk);

        $formatSpecValue = function ($value, bool $withCm = true) {
            $value = trim((string) ($value ?? ''));
            if ($value === '') {
                return '-';
            }

            return $withCm && is_numeric($value) ? $value . ' CM' : strtoupper($value);
        };

        $formatSpecValueNoDecimal = function ($value, bool $withCm = true) {
            $value = trim((string) ($value ?? ''));
            if ($value === '') {
                return '-';
            }
            if (is_numeric($value)) {
                $value = number_format((float)$value, 0, '', '');
                return $withCm ? $value . ' CM' : $value;
            }
            return strtoupper($value);
        };

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
            $fallbackColors = $colors->isNotEmpty() ? $colors : $tableColors;
            if ($fallbackColors->isEmpty()) {
                $fallbackColors = collect(['']);
            }

            $photoItems = $fallbackColors
                ->map(fn($label) => [
                    'label' => trim((string) $label),
                    'image' => $defaultImageSrc,
                ])
                ->take(5)
                ->values();
        }
    @endphp

    <!-- Header luar border -->
    <div style="text-align: center; margin-bottom: 4mm;">
        <div style="font-size: 13.5pt; font-weight: 700; color: #111827; letter-spacing: 0.5px; line-height: 1.25;">iLook</div>
        <div style="font-size: 9.5pt; color: #4b5563; line-height: 1.2;">jakarta</div>
        <div style="font-size: 9.5pt; color: #4b5563; line-height: 1.2; margin-top: 0.5mm;">{{ $printedAt->format('d/m/Y H:i:s') }}</div>
    </div>

    <div class="sheet">
        <table class="main-grid">
            <tr>
                <td class="main-left">
                    <div class="material-list" style="min-height: 55mm;">
                        @if (($spkCutting->mode ?? 'biasa') === 'potong_kecil')
                            @if ($selectedSkus->isNotEmpty())
                                <table class="material-table-grid">
                                    <thead>
                                        <tr>
                                            <th colspan="3" class="bagian-title">DAFTAR SKU</th>
                                        </tr>
                                        <tr>
                                            <th>NAMA SKU</th>
                                            <th>WARNA</th>
                                            <th>QTY (PCS)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($selectedSkus as $skuItem)
                                            <tr>
                                                <td class="nama-bahan-cell">{{ strtoupper($skuItem['sku_name']) }}</td>
                                                <td class="warna-cell">{{ strtoupper($skuItem['warna']) }}</td>
                                                <td class="qty-cell">{{ rtrim(rtrim(number_format($skuItem['qty'], 2, ',', '.'), '0'), ',') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="placeholder">BELUM ADA SKU YANG DIPILIH</div>
                            @endif
                        @else
                            @if ($bagianTables->isNotEmpty())
                                @php
                                    $maxRows = $bagianTables->max(fn($bagianTable) => $bagianTable['rows']->count()) ?: 0;
                                @endphp
                                <table class="material-table-grid">
                                    <thead>
                                        <tr>
                                            @foreach ($bagianTables as $bagianTable)
                                                <th colspan="3" class="bagian-title">{{ $bagianTable['nama_bagian'] }}</th>
                                            @endforeach
                                        </tr>
                                        <tr>
                                            @foreach ($bagianTables as $bagianTable)
                                                <th>NAMA BAHAN</th>
                                                <th>WARNA</th>
                                                <th>ROL</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for ($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++)
                                            <tr>
                                                @foreach ($bagianTables as $bagianTable)
                                                    @php
                                                        $row = $bagianTable['rows']->get($rowIndex);
                                                    @endphp
                                                    <td class="nama-bahan-cell">@if($row){{ strtoupper($row['nama']) }}@else&nbsp;@endif</td>
                                                    <td class="warna-cell">@if($row){{ strtoupper($row['warna']) }}@else&nbsp;@endif</td>
                                                    <td class="qty-cell">@if($row){{ rtrim(rtrim(number_format($row['qty'], 2, ',', '.'), '0'), ',') }}@else&nbsp;@endif</td>
                                                @endforeach
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            @else
                                <div class="placeholder">BELUM ADA BAHAN</div>
                            @endif
                        @endif
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
                        <!-- Barcode dipindahkan ke bawah Batas Kirim -->
                        <tr>
                            <td colspan="4" class="qr-code-cell" style="height: 28mm; text-align: center; vertical-align: middle; padding: 2mm 0;">
                                <div style="display: inline-block; text-align: center;">
                                    <img src="data:image/png;base64,{{ $qrBase64 }}" style="width: 20mm; height: 20mm; display: block; margin: 0 auto 1.5mm;">
                                    <div style="font-size: 7.2pt; font-weight: bold; letter-spacing: 0.5px; color: #1e293b; line-height: 1;">{{ $spkCutting->barcode }}</div>
                                </div>
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

        @if (($spkCutting->mode ?? 'biasa') !== 'potong_kecil')
        <div class="size-banner">
            <table class="size-info-table">
                <tr>
                    <td>
                        <div class="size-banner-line">BAGI {{ $sizeCount }} SIZE</div>
                    </td>
                    <td>
                        <div class="size-spec-title">SPESIFIKASI TEKNIS</div>
                        <div class="size-spec-grid">
                            @if($sizes->contains('S') || $sizes->contains('ALL SIZE')) LD S: {{ $formatSpecValue($productList?->id_s ?? $legacyProduk?->ld_s) }}<br> @endif
                            @if($sizes->contains('M') || $sizes->contains('ALL SIZE')) LD M: {{ $formatSpecValue($productList?->id_m ?? $legacyProduk?->ld_m) }}<br> @endif
                            @if($sizes->contains('L') || $sizes->contains('ALL SIZE')) LD L: {{ $formatSpecValue($productList?->id_l ?? $legacyProduk?->ld_l) }}<br> @endif
                            @if($sizes->contains('XL') || $sizes->contains('ALL SIZE')) LD XL: {{ $formatSpecValue($productList?->id_xl ?? $legacyProduk?->ld_xl) }}<br> @endif
                            PJ DRESS: {{ $formatSpecValueNoDecimal($productList?->pj_dress ?? $legacyProduk?->pj_dress) }}<br>
                            PJ CELANA: {{ $formatSpecValueNoDecimal($productList?->pj_celana ?? $legacyProduk?->pj_celana) }}<br>
                            PJ BAJU: {{ $formatSpecValueNoDecimal($productList?->pj_baju ?? $legacyProduk?->pj_baju) }}
                        </div>
                    </td>
                </tr>
            </table>
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
        @endif
    </div>
</body>

</html>
