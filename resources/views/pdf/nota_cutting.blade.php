<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Pendapatan Cutting</title>
    <style>
        body {
            background-color: #f3f4f6;
            padding: 24px;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 800px;
            margin: auto;
            background-color: white;
            padding: 32px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .title {
            text-align: center;
            border-bottom: 2px solid #667eea;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        h2 {
            font-size: 24px;
            color: #667eea;
            margin: 0;
        }

        .info-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .info {
            flex: 1;
        }

        .info p {
            font-size: 14px;
            margin: 8px 0;
            color: #374151;
        }

        .info strong {
            color: #1f2937;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }

        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            font-size: 13px;
        }

        th {
            background-color: #667eea;
            color: white;
            border: 1px solid #667eea;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            font-size: 13px;
        }

        .total {
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            margin-top: 16px;
            color: #374151;
        }

        .final-total {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 2px solid #e5e7eb;
        }

        .periode {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-top: 8px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="title">
            <h2>INVOICE PENDAPATAN CUTTING</h2>
            <p class="periode">{{ $periodeAwal->format('d M Y') }} -
                {{ $periodeAkhir->format('d M Y') }}</p>
        </div>

        <div class="info-container">
            <div class="info">
                <p><strong>Tukang Cutting:</strong> {{ $tukangCutting->nama_tukang_cutting ?? 'Tidak Ada' }}</p>
                <p><strong>ID Pendapatan:</strong> #{{ $pendapatan->id }}</p>
                <p><strong>Tanggal Pembayaran:</strong>
                    {{ $pendapatan->created_at ? $pendapatan->created_at->format('d M Y') : '-' }}</p>
            </div>
            <div class="info">
                <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $pendapatan->status_pembayaran)) }}</p>
                @if ($tukangCutting && $tukangCutting->bank)
                    <p><strong>Bank:</strong> {{ $tukangCutting->bank }}</p>
                @endif
                @if ($tukangCutting && $tukangCutting->no_rekening)
                    <p><strong>No. Rekening:</strong> {{ $tukangCutting->no_rekening }}</p>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>SPK Cutting</th>
                    <th>Nama Produk</th>
                    <th>Tanggal</th>
                    <th>Jumlah Komponen</th>
                    <th>Total Bayar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hasilCutting as $index => $hasil)
                    @php
                        // Load relasi jika belum ter-load
                        if (!$hasil->relationLoaded('spkCutting')) {
                            $hasil->load('spkCutting.produk');
                        }
                        $spkCutting = $hasil->spkCutting ?? null;
                        $produk = $spkCutting->produk ?? null;
                        $namaProduk = $produk->nama_produk ?? '-';
                        // Gunakan id_spk_cutting, jika tidak ada gunakan id sebagai fallback
                        $spkNumber = $spkCutting ? $spkCutting->id_spk_cutting ?? 'SPK-' . $spkCutting->id : '-';
                        $totalBayar = $hasil->total_bayar ?? ($hasil->total_hasil_pendapatan ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $spkNumber }}</td>
                        <td>{{ $namaProduk }}</td>
                        <td>{{ $hasil->created_at ? $hasil->created_at->format('d M Y') : '-' }}</td>
                        <td>{{ $hasil->jumlah_komponen ?? 0 }}</td>
                        <td>Rp {{ number_format($totalBayar, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="total">Total Pendapatan: Rp {{ number_format($pendapatan->total_pendapatan, 0, ',', '.') }}</p>

        @if ($pendapatan->total_hutang > 0 || $pendapatan->total_cashbon > 0)
            <table>
                <thead>
                    <tr>
                        <th>Jenis Potongan</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($pendapatan->total_hutang > 0)
                        <tr>
                            <td>Potongan Hutang</td>
                            <td>Rp {{ number_format($pendapatan->total_hutang, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    @if ($pendapatan->total_cashbon > 0)
                        <tr>
                            <td>Potongan Cashbon</td>
                            <td>Rp {{ number_format($pendapatan->total_cashbon, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <p class="total">Total Potongan: Rp
                {{ number_format($pendapatan->total_hutang + $pendapatan->total_cashbon, 0, ',', '.') }}</p>
        @endif

        <p class="final-total">Total Transfer: Rp {{ number_format($pendapatan->total_transfer, 0, ',', '.') }}</p>

    </div>
</body>

</html>
