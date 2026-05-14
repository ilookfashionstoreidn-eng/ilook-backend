<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Detail Produk</title>
    <style>
        @page {
            margin: 12mm;
            size: A4 portrait;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: DejaVu Sans, Arial, sans-serif;
        }

        body {
            background: #f5f5f5;
            color: #222;
            font-size: 11px;
            padding: 0;
            line-height: 1.35;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #dcdcdc;
            padding: 18px;
        }

        .header {
            border-bottom: 1px solid #cfcfcf;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .header h1 {
            font-size: 18px;
            margin-bottom: 4px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .header p {
            font-size: 10px;
            color: #666;
        }

        .section {
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 7px;
            padding-bottom: 4px;
            border-bottom: 1px solid #dcdcdc;
        }

        .product-wrapper {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .product-wrapper td {
            vertical-align: top;
        }

        .product-left {
            border: none;
            padding: 0 14px 0 0;
        }

        .product-photo-cell {
            width: 275px;
            border: none;
            padding: 0;
        }

        .product-photo {
            width: 275px;
            height: 275px;
            border: 1px solid #cfcfcf;
            background: #fafafa;
            overflow: hidden;
        }

        .product-photo img {
            width: 100%;
            height: 100%;
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #efefef;
            border: 1px solid #cfcfcf;
            padding: 6px 7px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
        }

        td {
            border: 1px solid #cfcfcf;
            padding: 6px 7px;
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        .info-label {
            width: 180px;
            background: #f7f7f7;
            font-weight: 700;
        }

        .summary-table th:nth-child(2),
        .summary-table td:nth-child(2) {
            width: 220px;
        }

        .total-row td {
            font-weight: 700;
        }

        .sku-table th:nth-child(1),
        .sku-table td:nth-child(1) {
            width: 6%;
            text-align: center;
        }

        .sku-table th:nth-child(2),
        .sku-table td:nth-child(2) {
            width: 44%;
        }

        .sku-table th:nth-child(3),
        .sku-table td:nth-child(3) {
            width: 25%;
        }

        .sku-table th:nth-child(4),
        .sku-table td:nth-child(4) {
            width: 25%;
        }

        .component-table th:nth-child(1),
        .component-table td:nth-child(1) {
            width: 20%;
        }

        .component-table th:nth-child(2),
        .component-table td:nth-child(2) {
            width: 28%;
        }

        .component-table th:nth-child(3),
        .component-table td:nth-child(3) {
            width: 17%;
        }

        .component-table th:nth-child(4),
        .component-table td:nth-child(4) {
            width: 10%;
        }

        .component-table th:nth-child(5),
        .component-table td:nth-child(5) {
            width: 10%;
        }

        .component-table th:nth-child(6),
        .component-table td:nth-child(6) {
            width: 15%;
        }

        .empty-row {
            text-align: center;
            color: #999;
            padding: 10px;
        }

        .spacer {
            height: 12px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .container {
                border: none;
                padding: 0;
            }

            .product-wrapper,
            .section,
            .header {
                page-break-inside: avoid;
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
            ['LD S', $produk->ld_s ?: '-'],
            ['LD M', $produk->ld_m ?: '-'],
            ['LD L', $produk->ld_l ?: '-'],
            ['LD XL', $produk->ld_xl ?: '-'],
            ['PJ Dress', $produk->pj_dress !== null ? $quantity($produk->pj_dress) : '-'],
            ['PJ Celana', $produk->pj_celana !== null ? $quantity($produk->pj_celana) : '-'],
            ['PJ Baju', $produk->pj_baju !== null ? $quantity($produk->pj_baju) : '-'],
        ];
    @endphp

    <div class="container">
        <div class="header">
            <h1>DETAIL PRODUK</h1>
            <p>Dicetak pada: {{ $tanggal }} - {{ $waktu }}</p>
        </div>

        <table class="product-wrapper">
            <tr>
                <td class="product-left">
                    <div class="section">
                        <div class="section-title">Informasi Produk</div>
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
                    </div>

                    <div class="section">
                        <div class="section-title">Rincian Harga</div>
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
                                    <td>Total Harga Komponen</td>
                                    <td class="text-right">{{ $currency($totalKomponen) }}</td>
                                </tr>
                                <tr class="total-row">
                                    <td><strong>TOTAL HPP</strong></td>
                                    <td class="text-right"><strong>{{ $currency($produk->hpp) }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>

                <td class="product-photo-cell">
                    <div class="product-photo">
                        @if ($gambarBase64)
                            <img src="{{ $gambarBase64 }}" alt="{{ $produk->nama_produk }}">
                        @else
                            <div class="photo-placeholder"><span>Tidak ada gambar</span></div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <div class="spacer"></div>

        <div class="section">
            <div class="section-title">SKU Produk</div>
            <table class="sku-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>SKU</th>
                        <th>Warna</th>
                        <th>Ukuran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($produk->skus as $sku)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $sku->sku ?: '-' }}</td>
                            <td>{{ $sku->warna ?: '-' }}</td>
                            <td>{{ $sku->ukuran ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-row">Tidak ada data SKU untuk produk ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Detail Komponen</div>
            <table class="component-table">
                <thead>
                    <tr>
                        <th>Jenis Komponen</th>
                        <th>Nama Bahan / Aksesoris</th>
                        <th class="text-right">Harga Satuan</th>
                        <th class="text-right">Jumlah</th>
                        <th>Satuan</th>
                        <th class="text-right">Total Harga</th>
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
                            <td class="text-right">{{ $currency($komp->harga_bahan) }}</td>
                            <td class="text-right">{{ $quantity($komp->jumlah_bahan) }}</td>
                            <td>{{ $satuan }}</td>
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
</body>

</html>
