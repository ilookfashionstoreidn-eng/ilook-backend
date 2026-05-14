<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Produk</title>
    <style>
        @page {
            margin: 10mm;
            size: A4 landscape;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: DejaVu Sans, Arial, sans-serif;
        }

        body {
            color: #222;
            font-size: 9px;
            line-height: 1.25;
        }

        .container {
            width: 100%;
            background: #fff;
        }

        .header {
            border-bottom: 1px solid #dcdcdc;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .header h1 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .header p {
            color: #666;
            font-size: 8px;
        }

        .panel {
            border: 1px solid #dcdcdc;
            background: #fff;
            margin-bottom: 8px;
        }

        .panel-title {
            background: #f7f7f7;
            border-bottom: 1px solid #dcdcdc;
            padding: 6px 8px;
            font-size: 9px;
            font-weight: 700;
        }

        .panel-body {
            padding: 7px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #dcdcdc;
            padding: 4px 5px;
            font-size: 8px;
            vertical-align: top;
        }

        th {
            background: #efefef;
            text-align: left;
            font-weight: 700;
        }

        .info-label {
            width: 42%;
            background: #fafafa;
            font-weight: 700;
        }

        .text-right {
            text-align: right;
        }

        .product-image {
            width: 100%;
            height: 255px;
            border: 1px solid #dcdcdc;
            background: #fafafa;
            overflow: hidden;
            padding: 8px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center center;
            display: block;
        }

        .photo-placeholder {
            width: 100%;
            height: 100%;
            display: table;
            color: #999;
        }

        .photo-placeholder span {
            display: table-cell;
            text-align: center;
            vertical-align: middle;
        }

        .empty-row {
            text-align: center;
            color: #999;
            padding: 10px;
        }

        .section-gap {
            height: 6px;
        }

        .summary-table th:nth-child(2),
        .summary-table td:nth-child(2) {
            width: 34%;
        }

        .component-table th:nth-child(1),
        .component-table td:nth-child(1) {
            width: 16%;
        }

        .component-table th:nth-child(2),
        .component-table td:nth-child(2) {
            width: 32%;
        }

        .component-table th:nth-child(3),
        .component-table td:nth-child(3) {
            width: 12%;
        }

        .component-table th:nth-child(4),
        .component-table td:nth-child(4) {
            width: 12%;
        }

        .component-table th:nth-child(5),
        .component-table td:nth-child(5) {
            width: 12%;
        }

        .component-table th:nth-child(6),
        .component-table td:nth-child(6) {
            width: 16%;
        }

        .total-row td {
            font-weight: 700;
        }

        .sku-table th,
        .sku-table td {
            font-size: 7.5px;
            word-break: break-word;
        }

        .sku-table th:nth-child(1),
        .sku-table td:nth-child(1) {
            width: 56%;
        }

        .sku-table th:nth-child(2),
        .sku-table td:nth-child(2) {
            width: 24%;
        }

        .sku-table th:nth-child(3),
        .sku-table td:nth-child(3) {
            width: 20%;
        }

        .report-grid {
            width: 100%;
            clear: both;
        }

        .report-col {
            float: left;
            vertical-align: top;
            margin-right: 0.8%;
        }

        .col-info {
            width: 36.8%;
        }

        .col-image {
            width: 27.2%;
        }

        .col-sku {
            width: 34.4%;
            margin-right: 0;
        }

        .clearfix {
            clear: both;
        }

        .col-component-full {
            width: 100%;
            margin-right: 0;
            clear: both;
            float: none;
        }

        @media print {
            body {
                background: #fff;
            }

            .panel,
            tr,
            td {
                page-break-inside: auto !important;
                break-inside: auto !important;
            }
        }
    </style>
</head>

<body>
    @php
        $currency = function ($value) {
            return 'Rp ' . number_format((float) ($value ?? 0), 0, ',', '.');
        };

        $quantity = function ($value) {
            $formatted = number_format((float) ($value ?? 0), 3, ',', '.');
            return rtrim(rtrim($formatted, '0'), ',');
        };

        $componentLabel = function ($value) {
            $normalized = strtolower(trim((string) $value));
            $labels = [
                'atasan' => 'Bahan Utama',
                'bawahan' => 'Bahan Kombinasi',
                'bahan_utama' => 'Bahan Utama',
                'bahan_kombinasi' => 'Bahan Kombinasi',
                'fullbody' => 'Fullbody',
                'aksesoris' => 'Aksesoris',
            ];

            if (isset($labels[$normalized])) {
                return $labels[$normalized];
            }

            return $normalized !== '' ? ucwords(str_replace('_', ' ', $normalized)) : '-';
        };

        $infoRows = [
            ['ID Produk', '#' . $produk->id],
            ['Product Group', $produk->product_group ?: '-'],
            ['Nama Produk', $produk->nama_produk ?: '-'],
            ['Jenis Produk', strtoupper($produk->jenis_produk ?: '-')],
            ['Kategori', strtoupper($produk->kategori_produk ?: '-')],
            ['Status HPP', strtoupper($produk->status_produk ?: '-')],
        ];

        $sizeRows = [];

        foreach ([
            'LD S' => $produk->ld_s ?? null,
            'LD M' => $produk->ld_m ?? null,
            'LD L' => $produk->ld_l ?? null,
            'LD XL' => $produk->ld_xl ?? null,
            'PJ DRESS' => $produk->pj_dress ?? null,
            'PJ CELANA' => $produk->pj_celana ?? null,
            'PJ BAJU' => $produk->pj_baju ?? null,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $sizeRows[] = [$label, is_numeric($value) ? $quantity($value) : $value];
            }
        }

        if (empty($sizeRows)) {
            $sizeRows[] = ['-', '-'];
        }
    @endphp

    <div class="container">
        <div class="header">
            <h1>DETAIL PRODUK</h1>
            <p>Dicetak pada: {{ $tanggal }} - {{ $waktu }}</p>
        </div>

        <div class="report-grid">
            <div class="report-col col-info">
                <div class="panel">
                    <div class="panel-title">Informasi Produk & Rincian Harga</div>
                    <div class="panel-body">
                        <table>
                            <tbody>
                                @foreach ($infoRows as $row)
                                    <tr>
                                        <td class="info-label">{{ $row[0] }}</td>
                                        <td>{{ $row[1] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if (!empty($sizeRows))
                            <div class="section-gap"></div>
                            <table>
                                <thead>
                                    <tr>
                                        <th colspan="2">Informasi Ukuran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sizeRows as $row)
                                        <tr>
                                            <td class="info-label">{{ $row[0] }}</td>
                                            <td>{{ $row[1] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        <div class="section-gap"></div>
                        <table class="summary-table">
                            <thead>
                                <tr>
                                    <th>Keterangan</th>
                                    <th class="text-right">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Harga Jasa Cutting</td>
                                    <td class="text-right">{{ $currency($produk->harga_jasa_cutting) }}</td>
                                </tr>
                                <tr>
                                    <td>Harga Jasa CMT</td>
                                    <td class="text-right">{{ $currency($produk->harga_jasa_cmt) }}</td>
                                </tr>
                                <tr>
                                    <td>Harga Jasa Aksesoris</td>
                                    <td class="text-right">{{ $currency($produk->harga_jasa_aksesoris) }}</td>
                                </tr>
                                <tr>
                                    <td>Harga Overhead</td>
                                    <td class="text-right">{{ $currency($produk->harga_overhead) }}</td>
                                </tr>
                                <tr>
                                    <td>Total Komponen</td>
                                    <td class="text-right">{{ $currency($totalKomponen) }}</td>
                                </tr>
                                <tr class="total-row">
                                    <td><strong>TOTAL HPP</strong></td>
                                    <td class="text-right"><strong>{{ $currency($produk->hpp) }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="report-col col-image">
                <div class="panel">
                    <div class="panel-title">Gambar Produk</div>
                    <div class="panel-body">
                        <div class="product-image">
                            @if ($gambarBase64)
                                <img src="{{ $gambarBase64 }}" alt="{{ $produk->nama_produk }}">
                            @else
                                <div class="photo-placeholder">
                                    <span>Tidak ada gambar</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="report-col col-sku">
                <div class="panel">
                    <div class="panel-title">SKU</div>
                    <div class="panel-body">
                        <table class="sku-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Warna</th>
                                    <th>Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($produk->skus as $sku)
                                    <tr>
                                        <td>{{ $sku->sku ?: '-' }}</td>
                                        <td>{{ $sku->warna ?: '-' }}</td>
                                        <td>{{ $sku->ukuran ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="empty-row">Tidak ada data SKU untuk produk ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="report-col col-component-full">
                <div class="panel">
                    <div class="panel-title">Detail Komponen</div>
                    <div class="panel-body">
                        <table class="component-table">
                            <thead>
                                <tr>
                                    <th>Jenis</th>
                                    <th>Nama Bahan / Aksesoris</th>
                                    <th class="text-right">Qty</th>
                                    <th>Satuan</th>
                                    <th class="text-right">Harga</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($produk->komponen as $komp)
                                    @php
                                        $namaKomponen = '-';
                                        if ($komp->sumber_komponen === 'bahan' && $komp->bahan) {
                                            $namaKomponen = $komp->bahan->nama_bahan;
                                        } elseif ($komp->sumber_komponen === 'aksesoris' && $komp->aksesoris) {
                                            $namaKomponen = $komp->aksesoris->nama_aksesoris;
                                        }

                                        $satuan = $komp->satuan_bahan ?: '-';
                                        if ($satuan === '-' && $komp->sumber_komponen === 'aksesoris') {
                                            $satuan = 'pcs';
                                        }
                                        if ($satuan === '-' && $komp->bahan && !empty($komp->bahan->satuan)) {
                                            $satuan = $komp->bahan->satuan;
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $componentLabel($komp->jenis_komponen) }}</td>
                                        <td>{{ $namaKomponen }}</td>
                                        <td class="text-right">{{ $quantity($komp->jumlah_bahan) }}</td>
                                        <td>{{ $satuan }}</td>
                                        <td class="text-right">{{ $currency($komp->harga_bahan) }}</td>
                                        <td class="text-right">{{ $currency($komp->total_harga_bahan) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="empty-row">Tidak ada data komponen untuk produk ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="clearfix"></div>
        </div>
    </div>
</body>

</html>
