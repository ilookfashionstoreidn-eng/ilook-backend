<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk - {{ $produk->nama_produk }}</title>
    <style>
        @page {
            margin: 8mm;
            size: A4;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.4;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            text-align: center;
            margin-bottom: 10px;
            border-radius: 6px;
        }

        .header h1 {
            font-size: 18px;
            margin-bottom: 4px;
            font-weight: bold;
        }

        .header p {
            font-size: 9px;
            opacity: 0.95;
        }

        .container {
            padding: 0 5px;
        }

        .info-section {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 0;
            border-left: 4px solid #667eea;
        }

        .info-row {
            margin-bottom: 6px;
            padding: 3px 0;
        }

        .info-label {
            display: inline-block;
            width: 35%;
            font-weight: bold;
            color: #667eea;
            vertical-align: top;
            font-size: 9px;
        }

        .info-value {
            display: inline-block;
            width: 63%;
            vertical-align: top;
            font-size: 9px;
        }

        .harga-section {
            background: #fff;
            border: 2px solid #667eea;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 0;
        }

        .harga-title {
            font-size: 12px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 8px;
            text-align: center;
            padding-bottom: 6px;
            border-bottom: 2px solid #667eea;
        }

        .harga-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            margin-bottom: 8px;
        }

        .harga-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 9px;
            vertical-align: middle;
        }

        .harga-table .harga-label {
            font-weight: 500;
            color: #333;
        }

        .harga-table .harga-value {
            text-align: right;
            font-weight: bold;
            color: #667eea;
        }

        .total-row {
            width: 100%;
            border-collapse: collapse;
            background: #667eea;
            color: white;
            margin-top: 5px;
            border-radius: 4px;
        }

        .total-row td {
            border: none;
            padding: 8px 10px;
            font-size: 11px;
        }

        .total-label {
            font-weight: bold;
            font-size: 11px;
        }

        .total-value {
            text-align: right;
            font-weight: bold;
            font-size: 13px;
        }

        .komponen-section {
            margin-top: 10px;
        }

        .komponen-title {
            font-size: 12px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        table thead {
            background: #667eea;
            color: white;
        }

        table th {
            padding: 8px 6px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
        }

        table td {
            padding: 6px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 9px;
        }

        table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-urgent {
            background: #f5576c;
            color: white;
        }

        .status-normal {
            background: #4facfe;
            color: white;
        }

        .status-sementara {
            background: #ffecd2;
            color: #8b4513;
        }

        .status-fix {
            background: #a8edea;
            color: #2d5016;
        }

        .status-bermasalah {
            background: #ff9a9e;
            color: #8b0000;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>📦 DETAIL PRODUK</h1>
        <p>Dicetak pada: {{ $tanggal }} - {{ $waktu }}</p>
    </div>

    <div class="container">
        <!-- Layout 2 Kolom: Info Produk dan Rincian Harga -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 5px;">
                    <!-- Informasi Produk -->
                    <div class="info-section" style="margin-bottom: 0;">
                        @if ($gambarBase64)
                            <div style="text-align: center; margin-bottom: 10px; padding: 5px;">
                                <img src="{{ $gambarBase64 }}" alt="{{ $produk->nama_produk }}"
                                    style="max-width: 250px; max-height: 250px; width: auto; height: auto; border-radius: 8px; border: 3px solid #667eea; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); object-fit: contain; background: white; display: block; margin: 0 auto;" />
                            </div>
                        @endif
                        <div class="info-row">
                            <div class="info-label">ID Produk:</div>
                            <div class="info-value">#{{ $produk->id }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Nama Produk:</div>
                            <div class="info-value"><strong>{{ $produk->nama_produk }}</strong></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Jenis Produk:</div>
                            <div class="info-value">{{ $produk->jenis_produk }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Kategori:</div>
                            <div class="info-value">
                                <span
                                    class="status-badge {{ strtolower($produk->kategori_produk) === 'urgent' ? 'status-urgent' : 'status-normal' }}">
                                    {{ $produk->kategori_produk }}
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Status HPP:</div>
                            <div class="info-value">
                                @if ($produk->status_produk === 'Sementara')
                                    <span class="status-badge status-sementara">Sementara</span>
                                @elseif($produk->status_produk === 'Fix')
                                    <span class="status-badge status-fix">Fix</span>
                                @elseif($produk->status_produk === 'Bermasalah')
                                    <span class="status-badge status-bermasalah">Bermasalah</span>
                                @else
                                    <span class="status-badge">{{ $produk->status_produk ?? '-' }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 5px;">
                    <!-- Rincian Harga -->
                    <div class="harga-section" style="margin-bottom: 0;">
                        <div class="harga-title">💰 RINCIAN HARGA</div>

                        <table class="harga-table">
                            <tbody>
                                <tr>
                                    <td class="harga-label" style="width: 65%;">Harga Jasa Cutting:</td>
                                    <td class="harga-value" style="width: 35%;">Rp.
                                        {{ number_format($produk->harga_jasa_cutting ?? 0, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="harga-label" style="width: 65%;">Harga Jasa CMT:</td>
                                    <td class="harga-value" style="width: 35%;">Rp.
                                        {{ number_format($produk->harga_jasa_cmt ?? 0, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="harga-label" style="width: 65%;">Harga Jasa Aksesoris:</td>
                                    <td class="harga-value" style="width: 35%;">Rp.
                                        {{ number_format($produk->harga_jasa_aksesoris ?? 0, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="harga-label" style="width: 65%;">Harga Overhead:</td>
                                    <td class="harga-value" style="width: 35%;">Rp.
                                        {{ number_format($produk->harga_overhead ?? 0, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="harga-label" style="width: 65%;">Total Harga Komponen:</td>
                                    <td class="harga-value" style="width: 35%;">Rp.
                                        {{ number_format($totalKomponen, 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table class="total-row" style="width: 100%; border-collapse: collapse;">
                            <tbody>
                                <tr>
                                    <td class="total-label" style="width: 65%;">TOTAL HPP:</td>
                                    <td class="total-value" style="width: 35%;">Rp.
                                        {{ number_format($produk->hpp ?? 0, 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Detail Komponen -->
        @if ($produk->komponen && $produk->komponen->count() > 0)
            <div class="komponen-section">
                <div class="komponen-title">🔧 DETAIL KOMPONEN</div>

                <table>
                    <thead>
                        <tr>
                            <th>Jenis Komponen</th>
                            <th>Nama Bahan/Aksesoris</th>
                            <th>Harga Satuan</th>
                            <th>Jumlah</th>
                            <th>Satuan</th>
                            <th>Total Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($produk->komponen as $komp)
                            <tr>
                                <td><strong>{{ ucfirst($komp->jenis_komponen) }}</strong></td>
                                <td>
                                    @if ($komp->sumber_komponen === 'bahan' && $komp->bahan)
                                        {{ $komp->bahan->nama_bahan }}
                                    @elseif($komp->sumber_komponen === 'aksesoris' && $komp->aksesoris)
                                        {{ $komp->aksesoris->nama_aksesoris }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>Rp. {{ number_format($komp->harga_bahan ?? 0, 0, ',', '.') }}</td>
                                <td>{{ number_format($komp->jumlah_bahan ?? 0, 2, ',', '.') }}</td>
                                <td>{{ $komp->satuan_bahan ?? '-' }}</td>
                                <td><strong>Rp.
                                        {{ number_format($komp->total_harga_bahan ?? 0, 0, ',', '.') }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="komponen-section">
                <div class="komponen-title">🔧 DETAIL KOMPONEN</div>
                <p style="text-align: center; color: #999; padding: 15px; font-size: 10px;">Tidak ada data komponen
                    untuk
                    produk ini.</p>
            </div>
        @endif


    </div>
</body>

</html>
