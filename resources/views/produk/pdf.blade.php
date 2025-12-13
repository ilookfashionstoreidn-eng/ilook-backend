<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk - {{ $produk->nama_produk }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 11px;
            opacity: 0.9;
        }

        .container {
            padding: 0 20px;
        }

        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .info-row {
            margin-bottom: 10px;
            padding: 5px 0;
        }

        .info-label {
            display: inline-block;
            width: 40%;
            font-weight: bold;
            color: #667eea;
            vertical-align: top;
        }

        .info-value {
            display: inline-block;
            width: 58%;
            vertical-align: top;
        }

        .harga-section {
            background: #fff;
            border: 2px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .harga-title {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .harga-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 15px;
        }

        .harga-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 12px;
            vertical-align: top;
        }

        .harga-table .harga-label {
            font-weight: normal;
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
            margin-top: 10px;
        }

        .total-row td {
            border: none;
            padding: 12px 15px;
            font-size: 14px;
        }

        .total-label {
            font-weight: bold;
            font-size: 14px;
        }

        .total-value {
            text-align: right;
            font-weight: bold;
            font-size: 16px;
        }

        .komponen-section {
            margin-top: 30px;
        }

        .komponen-title {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table thead {
            background: #667eea;
            color: white;
        }

        table th {
            padding: 10px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
        }

        table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 11px;
        }

        table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 10px;
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
        <!-- Informasi Produk -->
        <div class="info-section">
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
                <div class="info-label">Kategori Produk:</div>
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

        <!-- Rincian Harga -->
        <div class="harga-section">
            <div class="harga-title">💰 RINCIAN HARGA</div>

            <table class="harga-table">
                <tbody>
                    <tr>
                        <td class="harga-label" style="width: 70%;">Harga Jasa Cutting:</td>
                        <td class="harga-value" style="width: 30%;">Rp.
                            {{ number_format($produk->harga_jasa_cutting ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="harga-label" style="width: 70%;">Harga Jasa CMT:</td>
                        <td class="harga-value" style="width: 30%;">Rp.
                            {{ number_format($produk->harga_jasa_cmt ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="harga-label" style="width: 70%;">Harga Jasa Aksesoris:</td>
                        <td class="harga-value" style="width: 30%;">Rp.
                            {{ number_format($produk->harga_jasa_aksesoris ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="harga-label" style="width: 70%;">Harga Overhead:</td>
                        <td class="harga-value" style="width: 30%;">Rp.
                            {{ number_format($produk->harga_overhead ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="harga-label" style="width: 70%;">Total Harga Komponen:</td>
                        <td class="harga-value" style="width: 30%;">Rp.
                            {{ number_format($totalKomponen, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="total-row" style="width: 100%; border-collapse: collapse;">
                <tbody>
                    <tr>
                        <td class="total-label" style="width: 70%;">TOTAL HPP:</td>
                        <td class="total-value" style="width: 30%;">Rp.
                            {{ number_format($produk->hpp ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

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
                                <td>{{ number_format($komp->jumlah_bahan ?? 0, 4, ',', '.') }}</td>
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
                <p style="text-align: center; color: #999; padding: 20px;">Tidak ada data komponen untuk produk ini.</p>
            </div>
        @endif

       
    </div>
</body>

</html>
