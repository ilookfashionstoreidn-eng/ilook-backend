<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK CMT PDF</title>
    <style>
        @page {
            size: A4;
            margin: 8mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            background-color: #fff;
            margin: 0;
            padding: 0;
            line-height: 1.35;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* Utility classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        
        /* Premium Layout Styling */
        .header-title-container {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        
        .brand-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        .doc-title {
            font-size: 11px;
            color: #64748b;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .meta-table {
            margin-bottom: 12px;
            border: 1px solid #cbd5e1;
        }

        .meta-table td {
            padding: 6px 8px;
            font-size: 10px;
            border: 1px solid #cbd5e1;
        }

        .meta-label {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
            width: 18%;
        }

        .meta-value {
            color: #0f172a;
        }

        .meta-value-highlight {
            font-weight: bold;
            color: #0f172a;
        }

        .deadline-box {
            background-color: #fff1f2;
            color: #be123c;
            font-weight: bold;
            text-align: center;
        }

        .days-box {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
            text-align: center;
        }

        /* Section Layouts */
        .section-table {
            margin-bottom: 12px;
        }

        .detail-table th {
            background-color: #334155;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px;
            border: 1px solid #475569;
        }

        .detail-table td {
            padding: 6px;
            border: 1px solid #cbd5e1;
            font-size: 9.5px;
        }

        .specs-table th {
            background-color: #475569;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px;
            border: 1px solid #64748b;
        }

        .specs-table td {
            padding: 5.5px;
            border: 1px solid #cbd5e1;
            font-size: 9px;
        }

        /* Card Image Grid */
        .image-card-table {
            margin-bottom: 12px;
        }

        .image-card-header {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-bottom: none;
            padding: 4px;
            font-size: 9px;
            font-weight: bold;
            color: #334155;
            text-align: center;
            text-transform: uppercase;
        }

        .image-card-body {
            border: 1px solid #cbd5e1;
            height: 250px;
            text-align: center;
            vertical-align: middle;
            background-color: #fafafa;
            padding: 6px;
        }

        .image-card-img {
            max-width: 100%;
            max-height: 240px;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .no-image-text {
            font-size: 9.5px;
            color: #94a3b8;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding-top: 100px;
        }

        /* Notes Block */
        .notes-container {
            border: 1px solid #cbd5e1;
            border-left: 3.5px solid #d97706; /* Warm amber side border */
            background-color: #fffbeb;
            padding: 8px 10px;
            margin-bottom: 15px;
        }

        .notes-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .notes-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .notes-item {
            font-size: 8.5px;
            color: #78350f;
            line-height: 1.35;
            margin-bottom: 4px;
            text-align: justify;
        }

        /* Signature Area */
        .signature-table {
            margin-top: 15px;
        }

        .signature-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #475569;
            margin-bottom: 45px;
        }

        .signature-line {
            width: 130px;
            border-bottom: 1px solid #94a3b8;
            margin: 0 auto;
        }

        .signature-name {
            font-size: 9.5px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    {{-- Ambil data produk dan product list dari relasi --}}
    @php
        $produk = null;
        $nomorSeri = null;
        $productList = null;

        if ($spk->source_type === 'cutting' && $spk->spkCuttingDistribusi) {
            $distribusi = $spk->spkCuttingDistribusi;
            $produk = $distribusi->spkCutting->produk ?? null;
            $nomorSeri = $distribusi->kode_seri;
            $productList = $distribusi->spkCutting->productList ?? null;
        } elseif ($spk->source_type === 'jasa' && $spk->spkJasa?->spkCuttingDistribusi) {
            $distribusi = $spk->spkJasa->spkCuttingDistribusi;
            $produk = $distribusi->spkCutting->produk ?? null;
            $nomorSeri = $distribusi->kode_seri;
            $productList = $distribusi->spkCutting->productList ?? null;
        }

        // Group warna di level blade untuk mencegah baris ganda (2x) jika data di DB duplikat
        $warnaList = $spk->warna->groupBy(function($w) {
            return strtoupper(trim($w->nama_warna));
        })->map(function ($items, $colorName) {
            return (object)[
                'nama_warna' => $colorName,
                'qty' => $items->sum('qty'),
            ];
        })->values();

        $totalQty = $warnaList->sum('qty');

        // Resolusi gambar per warna variant dari product list / detail
        $variantImages = collect();
        if (isset($distribusi) && $distribusi) {
            foreach ($distribusi->detail as $detail) {
                $sku = $detail->productListSku;
                if ($sku && $sku->product_colour) {
                    $imagePath = null;
                    if ($sku->product_list_image_id) {
                        $imgRecord = \DB::table('product_list_images')->where('id', $sku->product_list_image_id)->first();
                        if ($imgRecord && $imgRecord->image_path) {
                            $pathsToTry = [
                                public_path('storage/' . $imgRecord->image_path),
                                storage_path('app/public/' . $imgRecord->image_path),
                                public_path($imgRecord->image_path),
                            ];
                            foreach ($pathsToTry as $p) {
                                if (file_exists($p)) {
                                    $imagePath = $p;
                                    break;
                                }
                            }
                        }
                    }

                    // Fallback to main product image if variant image is missing
                    if (!$imagePath && $produk && $produk->gambar_produk) {
                        $mainImgPath = public_path('storage/' . $produk->gambar_produk);
                        if (file_exists($mainImgPath)) {
                            $imagePath = $mainImgPath;
                        }
                    }

                    if ($imagePath) {
                        $variantImages->put(strtolower(trim($sku->product_colour)), [
                            'color' => $sku->product_colour,
                            'path' => $imagePath
                        ]);
                    }
                }
            }
        }
    @endphp

    <!-- 1️⃣ HEADER TITLE BLOCK -->
    <div class="header-title-container">
        <table style="width: 100%; border: none;">
            <tr style="background: transparent;">
                <td style="width: 60%; border: none; padding: 0;">
                    <div class="brand-name">ILOOK</div>
                    <div class="doc-title">Surat Perintah Kerja CMT</div>
                </td>
                <td style="width: 40%; border: none; padding: 0; text-align: right; vertical-align: bottom;">
                    <div style="font-size: 11px; font-weight: bold; color: #475569; letter-spacing: 0.5px;">
                        NO. SERI: <span style="color: #0f172a; font-size: 12px; font-weight: 800;">{{ strtoupper($nomorSeri ?? '–') }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- 2️⃣ META DETAILS GRID -->
    <table class="meta-table">
        <tr>
            <td class="meta-label">NAMA CMT</td>
            <td class="meta-value font-bold" style="width: 32%; font-size: 10.5px;">{{ strtoupper($spk->penjahit?->nama_penjahit ?? '–') }}</td>
            <td class="meta-label text-center" style="width: 16%; color: #9f1239; background-color: #fff1f2;">BATAS KIRIM</td>
            <td class="meta-label text-center" style="width: 18%;">TANGGAL AMBIL</td>
            <td class="meta-label text-center" style="width: 16%;">WAKTU PENGERJAAN</td>
        </tr>
        <tr>
            <td class="meta-label">NAMA PRODUCT</td>
            <td class="meta-value font-bold" style="font-size: 10.5px;">
                {{ strtoupper($spk->nama_produk ?? '–') }}
                @if($spk->product_size)
                    <span style="font-weight: normal; color: #475569; font-size: 9.5px;">({{ strtoupper($spk->product_size) }})</span>
                @endif
            </td>
            <td rowspan="2" class="deadline-box" style="vertical-align: middle; font-size: 10.5px;">
                {{ $spk->deadline ? \Carbon\Carbon::parse($spk->deadline)->locale('id')->isoFormat('dddd, D MMMM Y') : '–' }}
            </td>
            <td rowspan="2" class="text-center font-bold" style="vertical-align: middle; font-size: 10px; color: #1e293b;">
                {{ $spk->tanggal_ambil ? \Carbon\Carbon::parse($spk->tanggal_ambil)->locale('id')->isoFormat('dddd, D MMMM Y') : '–' }}
            </td>
            <td rowspan="2" class="days-box" style="vertical-align: middle;">
                <span style="font-size: 16px; font-weight: 800; color: #0f172a;">
                    {{ ($spk->deadline && $spk->tanggal_ambil) ? \Carbon\Carbon::parse($spk->tanggal_ambil)->startOfDay()->diffInDays(\Carbon\Carbon::parse($spk->deadline)->startOfDay()) : ($spk->waktu_pengerjaan ?? '–') }}
                </span>
                <span style="font-size: 9px; display: block; color: #64748b; font-weight: normal; margin-top: 2px;">HARI</span>
            </td>
        </tr>
        <tr>
            <td class="meta-label">HARGA JASA</td>
            <td class="meta-value font-bold" style="font-size: 11px; color: #047857; background-color: #f0fdf4;">
                Rp {{ number_format($spk->harga_per_jasa, 0, ',', '.') }}
            </td>
        </tr>
    </table>

    <!-- 3️⃣ MIDDLE SECTION: DETAILS + SPECS SIDE-BY-SIDE -->
    <table class="section-table" style="border: none;">
        <tr style="background: transparent;">
            <!-- Left Side: Warna / Qty / Aksesoris Table (70% width) -->
            <td style="width: 70%; border: none; padding: 0; padding-right: 10px; vertical-align: top;">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th style="width: 7%; text-align: center;">NO</th>
                            <th style="width: 38%; text-align: left;">VARIAN WARNA</th>
                            <th style="width: 15%; text-align: center;">QTY</th>
                            <th style="width: 40%; text-align: left;">AKSESORIS YANG DISERAHKAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Format aksesoris dari string JSON
                            $aksesorisArr = [];
                            if ($spk->aksesoris) {
                                try {
                                    $aksesorisArr = json_decode($spk->aksesoris, true);
                                    if (!is_array($aksesorisArr)) {
                                        $aksesorisArr = [];
                                    }
                                } catch (\Exception $e) {
                                    $aksesorisArr = [];
                                }
                            }
                            
                            $aksesorisStrings = [];
                            foreach ($aksesorisArr as $aks) {
                                if (!empty($aks['nama'])) {
                                    $aksesorisStrings[] = $aks['nama'] . ' (' . ($aks['jumlah'] ?? '0') . ' ' . ($aks['satuan'] ?? 'pcs') . ')';
                                }
                            }
                        @endphp
                        
                        @for ($i = 0; $i < 5; $i++)
                            @php
                                $rowWarna = $warnaList->get($i);
                                $rowAks = $aksesorisStrings[$i] ?? '';
                                $rowBg = ($i % 2 === 0) ? '#ffffff' : '#f8fafc';
                            @endphp
                            <tr style="background-color: {{ $rowBg }};">
                                <td class="text-center" style="color: #64748b; font-weight: bold; height: 18px;">
                                    {{ $i + 1 }}
                                </td>
                                <td class="font-bold" style="color: #0f172a;">
                                    {{ $rowWarna ? strtoupper($rowWarna->nama_warna) : '' }}
                                </td>
                                <td class="text-center font-bold" style="color: #0f172a;">
                                    {{ $rowWarna ? number_format($rowWarna->qty, 0, ',', '.') : '' }}
                                </td>
                                <td style="color: #475569; font-size: 8.5px;">
                                    {{ $rowAks }}
                                </td>
                            </tr>
                        @endfor
                        
                        <!-- Total Row -->
                        <tr style="background-color: #f1f5f9; font-weight: bold;">
                            <td colspan="2" class="text-center" style="font-size: 9px; color: #475569;">
                                TOTAL PRODUKSI
                            </td>
                            <td class="text-center" style="font-size: 10px; color: #0f172a; font-weight: 800;">
                                {{ number_format($totalQty, 0, ',', '.') }}
                            </td>
                            <td style="color: #64748b; font-size: 8.5px; font-style: italic; font-weight: normal;">
                                * Periksa kesesuaian fisik aksesoris
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
            
            <!-- Right Side: Specs Box (30% width) -->
            <td style="width: 30%; border: none; padding: 0; vertical-align: top;">
                <table class="specs-table">
                    <thead>
                        <tr>
                            <th colspan="2" style="background-color: #475569; border-color: #475569;">
                                SPESIFIKASI UKURAN
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Format dynamic specification values cleanly
                            $cleanLd = $productList && $productList->ld ? (str_contains(strtoupper($productList->ld), 'CM') ? $productList->ld : (float)$productList->ld . ' CM') : '–';
                            $cleanPjDress = $productList && $productList->pj_dress ? (str_contains(strtoupper($productList->pj_dress), 'CM') ? $productList->pj_dress : (float)$productList->pj_dress . ' CM') : '–';
                            $cleanPjCelana = $productList && $productList->pj_celana ? (str_contains(strtoupper($productList->pj_celana), 'CM') ? $productList->pj_celana : $productList->pj_celana . ' CM') : '–';
                            $cleanPjBaju = $productList && $productList->pj_baju ? (str_contains(strtoupper($productList->pj_baju), 'CM') ? $productList->pj_baju : (float)$productList->pj_baju . ' CM') : '–';
                        @endphp
                        <tr>
                            <td class="font-bold" style="background-color: #f8fafc; color: #475569; width: 50%;">LD (Lebar Dada)</td>
                            <td class="text-center font-bold" style="color: #0f172a;">{{ $cleanLd }}</td>
                        </tr>
                        <tr>
                            <td class="font-bold" style="background-color: #f8fafc; color: #475569;">PJ DRESS</td>
                            <td class="text-center font-bold" style="color: #0f172a;">{{ $cleanPjDress }}</td>
                        </tr>
                        <tr>
                            <td class="font-bold" style="background-color: #f8fafc; color: #475569;">PJ CELANA</td>
                            <td class="text-center font-bold" style="color: #0f172a;">{{ $cleanPjCelana }}</td>
                        </tr>
                        <tr>
                            <td class="font-bold" style="background-color: #f8fafc; color: #475569;">PJ BAJU</td>
                            <td class="text-center font-bold" style="color: #0f172a;">{{ $cleanPjBaju }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- 4️⃣ PRODUCT VARIANT IMAGES GALLERY -->
    <div style="font-size: 9.5px; font-weight: bold; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; margin-top: 10px;">
        Visual Referensi Produk / Varian Warna
    </div>
    <table class="image-card-table" style="width: {{ count($warnaList->pluck('nama_warna')->all()) === 1 ? '45%' : '100%' }}; margin: 0 auto;">
        <tr>
            @php
                $activeColors = $warnaList->pluck('nama_warna')->all();
                $colorCount = count($activeColors);
                if ($colorCount === 1) {
                    $colWidth = 100;
                } else {
                    $colWidth = $colorCount > 0 ? (100 / $colorCount) : 100;
                }
            @endphp
            @foreach ($activeColors as $colColor)
                <td style="width: {{ $colWidth }}%; padding: 0 4px; border: none; background: transparent; vertical-align: top;">
                    <div class="image-card-header">
                        {{ strtoupper($colColor) }}
                    </div>
                    <div class="image-card-body">
                        @php
                            $colorKey = strtolower(trim($colColor));
                            $imgInfo = $variantImages->get($colorKey);
                        @endphp
                        @if ($imgInfo && $imgInfo['path'] && file_exists($imgInfo['path']))
                            <img src="{{ $imgInfo['path'] }}" class="image-card-img">
                        @else
                            <div class="no-image-text">
                                <div style="font-size: 16px; margin-bottom: 4px;">📷</div>
                                TIDAK ADA FOTO
                            </div>
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    <!-- CATATAN & ATRIBUT SPK -->
    <div style="margin-top: 10px; margin-bottom: 5px;">
        <div style="border: 1px solid #cbd5e1; border-left: 3.5px solid #0f172a; background-color: #f8fafc; padding: 6px 8px; min-height: 50px;">
            <div style="font-size: 9.5px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">
                📝 Catatan Khusus SPK
            </div>
            <div style="font-size: 8.5px; color: #334155; line-height: 1.35;">
                @if(!empty($spk->catatan))
                    {!! nl2br(e($spk->catatan)) !!}
                @else
                    <span style="color: #94a3b8; font-style: italic;">Tidak ada catatan khusus untuk transaksi ini.</span>
                @endif
            </div>
            @if(!empty($spk->keterangan))
                <div style="margin-top: 6px; border-top: 1px dashed #cbd5e1; padding-top: 4px;">
                    <strong style="font-size: 8px; color: #475569; text-transform: uppercase;">Keterangan:</strong>
                    <span style="font-size: 8px; color: #1e293b;">{{ $spk->keterangan }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- 5️⃣ IMPORTANT NOTICE / NOTE LAINNYA -->
    <div class="notes-container" style="margin-top: 10px;">
        <div class="notes-title">📌 Ketentuan & Catatan Kerja CMT (Penting):</div>
        <table style="width: 100%; margin: 0; border: none; border-collapse: collapse; background: transparent;">
            <tr style="background: transparent;">
                <td style="width: 3%; vertical-align: top; font-weight: bold; font-size: 8.5px; padding: 1px 0; border: none; color: #78350f;">1.</td>
                <td class="notes-item" style="border: none;">
                    <strong>Sampel asli tidak boleh hilang.</strong> Jika sampel hilang, CMT wajib mengganti kerugian sebesar <strong>Rp 500.000,- (Lima Ratus Ribu Rupiah)</strong>. Pengembalian sampel dilakukan di hari pertama pengiriman barang jadi ke CMT. Jika pengembalian sampel tertunda, otomatis akan dipotong klaim sebesar nominal tersebut dari tagihan CMT. Menandatangani SPK ini berarti CMT setuju dengan segala ketentuan tertulis.
                </td>
            </tr>
            <tr style="background: transparent;">
                <td style="width: 3%; vertical-align: top; font-weight: bold; font-size: 8.5px; padding: 1px 0; border: none; color: #78350f;">2.</td>
                <td class="notes-item" style="border: none;">
                    <strong>Laporan & Overtime.</strong> Batas pelaporan masalah setelah pengambilan SPK adalah <strong>2-3 hari</strong>. Jika tidak ada laporan, pekerjaan dianggap lengkap tanpa kendala. Keterlambatan pengiriman (overtime) tanpa alasan logis/konfirmasi tertulis akan dikenakan pemotongan nilai tagihan (claim).
                </td>
            </tr>
        </table>
    </div>

    <!-- 6️⃣ SIGNATURE AREA -->
    <table class="signature-table" style="width: 100%; border: none; background: transparent;">
        <tr style="background: transparent;">
            <td style="width: 33%; text-align: center; border: none; padding: 0; vertical-align: top;">
                <div class="signature-title">Dibuat Oleh,</div>
                <div style="height: 50px;"></div>
                <div class="signature-line"></div>
                <div class="signature-name">Sendy</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 1px;">Tim Produksi</div>
            </td>
            <td style="width: 33%; text-align: center; border: none; padding: 0; vertical-align: top;">
                <div class="signature-title">Mengetahui,</div>
                <div style="height: 50px;"></div>
                <div class="signature-line"></div>
                <div class="signature-name">&nbsp;</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 1px;">SPV / Manager</div>
            </td>
            <td style="width: 33%; text-align: center; border: none; padding: 0; vertical-align: top;">
                <div class="signature-title">Diterima Oleh CMT,</div>
                <div style="height: 50px;"></div>
                <div class="signature-line"></div>
                <div class="signature-name">{{ strtoupper($spk->penjahit?->nama_penjahit ?? '–') }}</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 1px;">Mitra CMT</div>
            </td>
        </tr>
    </table>
</body>

</html>
