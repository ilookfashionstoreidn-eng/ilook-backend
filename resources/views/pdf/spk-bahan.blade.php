<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SPK Bahan PDF</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9px;
            color: #0f172a;
            background: #ffffff;
        }

        .sheet {
            page-break-after: always;
            page-break-inside: avoid;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .spk-section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .spk-section:last-child {
            margin-bottom: 0;
        }

        .spk-section-title {
            background: #f1f5f9;
            border: 1px solid #dbeafe;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            padding: 8px 10px;
            font-size: 9px;
            font-weight: 700;
            color: #1e3a8a;
            line-height: 1.3;
            page-break-after: avoid;
        }

        .header {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 10px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header-left {
            width: 70%;
            vertical-align: top;
            padding-right: 14px;
        }

        .header-right {
            width: 30%;
            vertical-align: top;
            text-align: right;
            border-left: 1px solid #dbeafe;
            padding-left: 14px;
        }

        .company {
            margin: 0 0 5px;
            color: #1d4ed8;
            font-size: 17px;
            line-height: 1.1;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .doc-title {
            margin: 0 0 6px;
            font-size: 14px;
            line-height: 1.2;
            font-weight: 700;
            color: #020617;
        }

        .doc-meta {
            margin: 0;
            color: #334155;
            font-size: 9px;
            line-height: 1.5;
        }

        .meta-label {
            display: block;
            margin: 0 0 4px;
            color: #64748b;
            font-size: 8px;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .meta-value {
            display: block;
            margin: 0 0 10px;
            color: #020617;
            font-size: 12px;
            line-height: 1.2;
            font-weight: 700;
        }

        .table-wrap {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
            page-break-before: avoid;
            page-break-inside: avoid;
        }

        .spk-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .spk-table th {
            background: #eff6ff;
            color: #1e3a8a;
            border-right: 1px solid #dbeafe;
            border-bottom: 1px solid #bfdbfe;
            padding: 8px 5px;
            font-size: 8px;
            line-height: 1.25;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-align: center;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .spk-table td {
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 6px;
            font-size: 9px;
            line-height: 1.45;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .spk-table th:last-child,
        .spk-table td:last-child {
            border-right: none;
        }

        .spk-table tr:last-child td {
            border-bottom: none;
        }

        .text-center {
            text-align: center;
        }

        .image-box {
            width: 200px;
            height: 200px;
            margin: 0 auto;
            text-align: center;
            overflow: hidden;
        }

        .image-box img {
            width: 200px;
            height: 200px;
            object-fit: contain;
            display: block;
            border: 1px solid #dbeafe;
            border-radius: 6px;
            background: #f8fafc;
        }

        .image-placeholder {
            display: block;
            width: 200px;
            height: 200px;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding-top: 88px;
            background: #f8fafc;
            color: #64748b;
            font-size: 8px;
            line-height: 1.3;
            text-align: center;
            box-sizing: border-box;
        }

        .bahan-name {
            display: block;
            margin-bottom: 4px;
            font-size: 10px;
            line-height: 1.3;
            font-weight: 700;
            color: #020617;
        }

        .bahan-meta {
            display: block;
            margin-bottom: 2px;
            font-size: 8px;
            line-height: 1.35;
            color: #334155;
        }

        .bahan-desc {
            display: block;
            margin-top: 2px;
            font-size: 8px;
            line-height: 1.35;
            color: #475569;
        }

        .plain-value {
            color: #0f172a;
            font-size: 9px;
            line-height: 1.35;
        }

        .subtotal-row td {
            background: #f8fafc;
            font-weight: 700;
            height: 52px;
            padding-top: 0;
            padding-bottom: 0;
            vertical-align: middle;
            page-break-inside: avoid;
        }

        .subtotal-row .plain-value,
        .subtotal-row td {
            line-height: 1.2;
        }

        .subtotal-label {
            text-align: right;
            color: #020617;
            padding-right: 12px !important;
        }

        .warna-name {
            color: #0f172a;
            font-size: 9px;
            line-height: 1.35;
        }

        .warna-list {
            margin: 0;
            padding-left: 14px;
        }

        .warna-list li {
            margin: 0 0 3px;
            padding-left: 1px;
            line-height: 1.35;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    @php
        // ADDED: Helper tampilan satuan agar tetap singkat dan rapi di DomPDF.
        $formatSatuan = function ($value) {
            $raw = trim((string) ($value ?? ''));

            if ($raw === '') {
                return '-';
            }

            $map = [
                'kg' => 'Kilogram',
                'g' => 'Gram',
                'gr' => 'Gram',
                'm' => 'Meter',
                'cm' => 'Centimeter',
                'mm' => 'Milimeter',
                'pcs' => 'Pcs',
                'roll' => 'Roll',
                'rol' => 'Rol',
                'ltr' => 'Liter',
                'l' => 'Liter',
            ];

            $label = $map[mb_strtolower($raw)] ?? ucfirst($raw);
            return $label . '/' . $raw;
        };

        // ADDED: Estimasi tinggi section PDF supaya blok SPK tidak pecah saat sisa halaman terlalu sempit.
        $estimateSpkSectionWeight = function ($spkBahan) {
            $detailRowCount = max(1, count($spkBahan->pdf_warna_detail ?? []));
            $baseWeight = 320;
            $extraDetailWeight = max(0, $detailRowCount - 1) * 36;

            return $baseWeight + $extraDetailWeight;
        };

        // ADDED: Pagination konservatif untuk menjaga satu blok SPK tetap utuh; maksimal tetap 2 SPK per halaman.
        $pageCapacity = 620;
        $maxSpkPerPage = 2;
        $spkPages = collect();
        $currentPage = collect();
        $currentWeight = 0;
        $renderedIndex = 0;

        foreach ($spkBahans->values() as $spkBahan) {
            $sectionWeight = $estimateSpkSectionWeight($spkBahan);

            if (
                $currentPage->isNotEmpty()
                && (
                    $currentPage->count() >= $maxSpkPerPage
                    || ($currentWeight + $sectionWeight) > $pageCapacity
                )
            ) {
                $spkPages->push($currentPage);
                $currentPage = collect();
                $currentWeight = 0;
            }

            $currentPage->push($spkBahan);
            $currentWeight += $sectionWeight;
        }

        if ($currentPage->isNotEmpty()) {
            $spkPages->push($currentPage);
        }
    @endphp
    @foreach ($spkPages as $pageIndex => $pageSpks)
    @php
        $pageSpkNumbers = $pageSpks->pluck('id')->map(fn ($id) => '#' . $id)->implode(', ');
        $pagePabrikNames = $pageSpks
            ->map(fn ($spk) => trim((string) ($spk->pabrik?->nama_pabrik ?? $spk->bahan?->pabrik_bahan ?? '')))
            ->filter()
            ->unique()
            ->values();
        $pageHeaderPabrik = $pagePabrikNames->count() === 1
            ? $pagePabrikNames->first()
            : ($pagePabrikNames->count() > 1 ? $pagePabrikNames->implode(', ') : '-');
    @endphp
    <div class="sheet">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <p class="company">{{ $companyName }}</p>
                        <p class="doc-title">Print SPK Pemesanan Bahan</p>
                        <p class="doc-meta">Pabrik: {{ $pageHeaderPabrik }}</p>
                    </td>
                    <td class="header-right">
                        <span class="meta-label">NOMOR SPK</span>
                        <span class="meta-value">{{ $pageSpkNumbers }}</span>
                        <span class="meta-label">TANGGAL CETAK</span>
                        <span class="meta-value">{{ $printedAt->format('d/m/Y H:i') }}</span>
                    </td>
                </tr>
            </table>
        </div>

        @foreach ($pageSpks as $chunkIndex => $spkBahan)
            @php
                $renderedIndex++;
                $globalIndex = $renderedIndex;
                $bahan = $spkBahan->bahan;
                $warnaRows = collect($spkBahan->pdf_warna_detail ?? []);
                if ($warnaRows->isEmpty()) {
                    $warnaRows = collect([
                        [
                            'warna' => '-',
                            'stok_dipesan' => 0,
                            'pesanan_dikirim' => 0,
                            'sisa_dipesan' => 0,
                        ],
                    ]);
                }
                $subtotal = $spkBahan->pdf_subtotal ?? [
                    'stok_dipesan' => 0,
                    'pesanan_dikirim' => 0,
                    'sisa_dipesan' => 0,
                ];
                $detailRowCount = $warnaRows->count();
                $namaPabrik = $spkBahan->pabrik?->nama_pabrik ?? $bahan?->pabrik_bahan ?? '-';
                $gambarBase64 = $spkBahan->bahan_image_base64 ?? null;
                $lamaPesan = $spkBahan->pdf_lama_pesan ?? null;
                $tanggalSpk = $spkBahan->tanggal_pemesanan ? \Carbon\Carbon::parse($spkBahan->tanggal_pemesanan)->format('d/m/Y') : $printedAt->format('d/m/Y');
            @endphp
            <div class="spk-section">
                <div class="spk-section-title">SPK {{ $spkBahan->id }} - {{ $namaPabrik }} - {{ $tanggalSpk }}</div>
                <div class="table-wrap">
                    <table class="spk-table">
                        <colgroup>
                            <col style="width: 4%;">
                            <col style="width: 22%;">
                            <col style="width: 21%;">
                            <col style="width: 7%;">
                            <col style="width: 15%;">
                            <col style="width: 8%;">
                            <col style="width: 8%;">
                            <col style="width: 5%;">
                            <col style="width: 5%;">
                            <col style="width: 5%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th style="width: 4%;">NO</th>
                                <th style="width: 22%;">GAMBAR</th>
                                <th style="width: 21%;">NAMA BAHAN</th>
                                <th style="width: 7%;">SATUAN</th>
                                <th style="width: 15%;">WARNA BAHAN</th>
                                <th style="width: 8%;">STOK DIPESAN</th>
                                <th style="width: 8%;">PENGIRIMAN</th>
                                <th style="width: 5%;">SISA DIPESAN</th>
                                <th style="width: 5%;">LEBIH KIRIM</th>
                                <th style="width: 5%;">LAMA PESAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($warnaRows as $index => $warna)
                                @php
                                    $stokDipesan = (int) data_get($warna, 'stok_dipesan', 0);
                                    $pesananDikirim = (int) data_get($warna, 'pesanan_dikirim', 0);
                                    $sisaDipesan = (int) data_get($warna, 'sisa_dipesan', max(0, $stokDipesan - $pesananDikirim));
                                    $lebihKirim = (int) data_get($warna, 'lebih_kirim', max(0, $pesananDikirim - $stokDipesan));
                                    $warnaNama = (string) data_get($warna, 'warna', '-');
                                @endphp
                                <tr>
                                    @if ($loop->first)
                                        <td class="text-center" style="width: 4%;" rowspan="{{ $detailRowCount }}">{{ $globalIndex }}</td>
                                        <td class="text-center" style="width: 22%;" rowspan="{{ $detailRowCount }}">
                                            <div class="image-box">
                                                @if ($gambarBase64)
                                                    <img src="{{ $gambarBase64 }}" alt="Gambar bahan">
                                                @else
                                                    <span class="image-placeholder">Tidak ada gambar</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td style="width: 21%;" rowspan="{{ $detailRowCount }}">
                                            <span class="bahan-name">{{ $bahan?->nama_bahan ?? '-' }}</span>
                                            <span class="bahan-meta">Kode/Supplier: {{ $bahan?->id ? '#' . $bahan->id : '-' }} / {{ $namaPabrik }}</span>
                                            <span class="bahan-meta">Grup: {{ $bahan?->group_bahan ?? '-' }}</span>
                                            <span class="bahan-desc">Deskripsi: {{ $bahan?->deskripsi ?: '-' }}</span>
                                        </td>
                                        <td class="text-center" style="width: 7%;" rowspan="{{ $detailRowCount }}">
                                            {{ $formatSatuan($bahan?->satuan) }}
                                        </td>
                                    @endif
                                    <td style="width: 15%;">
                                        <span class="warna-name">{{ $loop->iteration }}. {{ $warnaNama }}</span>
                                    </td>
                                    <td class="text-center" style="width: 8%;">
                                        <span class="plain-value">{{ $stokDipesan }}</span>
                                    </td>
                                    <td class="text-center" style="width: 8%;">
                                        <span class="plain-value">{{ $pesananDikirim }}</span>
                                    </td>
                                    <td class="text-center" style="width: 5%;">
                                        <span class="plain-value">{{ $sisaDipesan }}</span>
                                    </td>
                                    <td class="text-center" style="width: 5%;">
                                        <span class="plain-value">{{ $lebihKirim }}</span>
                                    </td>
                                    @if ($loop->first)
                                        <td class="text-center" style="width: 5%;" rowspan="{{ $detailRowCount }}">
                                            <span class="plain-value">{{ $lamaPesan === null ? '-' : ((int) $lamaPesan . ' hari') }}</span>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr class="subtotal-row">
                                <td class="subtotal-label" colspan="5">Subtotal SPK {{ $spkBahan->id }}</td>
                                <td class="text-center">
                                    <span class="plain-value">{{ number_format((int) data_get($subtotal, 'stok_dipesan', 0), 0, ',', '.') }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="plain-value">{{ number_format((int) data_get($subtotal, 'pesanan_dikirim', 0), 0, ',', '.') }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="plain-value">{{ number_format((int) data_get($subtotal, 'sisa_dipesan', 0), 0, ',', '.') }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="plain-value">{{ number_format((int) data_get($subtotal, 'lebih_kirim', 0), 0, ',', '.') }}</span>
                                </td>
                                <td class="text-center">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
    @endforeach
</body>
</html>
