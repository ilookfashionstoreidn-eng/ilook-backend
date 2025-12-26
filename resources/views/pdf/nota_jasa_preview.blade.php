<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Preview Pendapatan Jasa</title>
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
            border-bottom: 2px solid #f59e0b;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        h2 {
            font-size: 24px;
            color: #f59e0b;
            margin: 0;
        }

        .preview-badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
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
            background-color: #f59e0b;
            color: white;
            border: 1px solid #f59e0b;
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
            color: #f59e0b;
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
            <h2>INVOICE PREVIEW PENDAPATAN JASA <span class="preview-badge">PREVIEW</span></h2>
            <p class="periode">
                @if ($periodeAwal && $periodeAkhir)
                    {{ $periodeAwal instanceof \Carbon\Carbon ? $periodeAwal->format('d M Y') : date_format(date_create($periodeAwal), 'd M Y') }}
                    -
                    {{ $periodeAkhir instanceof \Carbon\Carbon ? $periodeAkhir->format('d M Y') : date_format(date_create($periodeAkhir), 'd M Y') }}
                @else
                    -
                @endif
            </p>
        </div>

        <div class="info-container">
            <div class="info">
                <p><strong>Tukang Jasa:</strong> {{ $tukangJasa->nama ?? 'Tidak Ada' }}</p>
                <p><strong>Status:</strong> <span style="color: #dc2626; font-weight: 600;">Belum Dibayar</span></p>
            </div>
            <div class="info">
                @if ($tukangJasa && $tukangJasa->bank)
                    <p><strong>Bank:</strong> {{ $tukangJasa->bank }}</p>
                @endif
                @if ($tukangJasa && $tukangJasa->no_rekening)
                    <p><strong>No. Rekening:</strong> {{ $tukangJasa->no_rekening }}</p>
                @endif
            </div>
        </div>

        @if (isset($hasilJasa) && $hasilJasa->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Distribusi Seri</th>
                        <th>Nama Produk</th>
                        <th>Tanggal</th>
                        <th>Jumlah Hasil</th>
                        <th>Jumlah Rusak</th>
                        <th>Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hasilJasa as $index => $hasil)
                        @php
                            // Gunakan pendekatan yang sama persis seperti nota_jasa.blade.php yang berfungsi
                            $distribusi = null;
                            $spkCutting = null;
                            $produk = null;
                            $kodeSeri = '-';
                            $namaProduk = '-';

                            if (isset($hasil->spkJasa) && $hasil->spkJasa) {
                                $distribusi =
                                    $hasil->spkJasa->spkCuttingDistribusi ??
                                    ($hasil->spkJasa->spk_cutting_distribusi ?? null);
                                if ($distribusi) {
                                    $kodeSeri = $distribusi->kode_seri ?? '-';
                                    $spkCutting = $distribusi->spkCutting ?? ($distribusi->spk_cutting ?? null);
                                    if ($spkCutting) {
                                        $produk = $spkCutting->produk ?? null;
                                        if ($produk) {
                                            $namaProduk = $produk->nama_produk ?? '-';
                                        }
                                    }
                                }
                            }
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $kodeSeri }}</td>
                            <td>{{ $namaProduk }}</td>
                            <td>{{ date_format(date_create($hasil->tanggal), 'd M Y') }}</td>
                            <td>{{ $hasil->jumlah_hasil ?? 0 }}</td>
                            <td>{{ $hasil->jumlah_rusak ?? 0 }}</td>
                            <td>Rp {{ number_format($hasil->total_pendapatan ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="text-align: center; color: #6b7280; padding: 20px;">
                Tidak ada detail hasil jasa untuk periode ini
            </p>
        @endif

        <p class="total">Total Pendapatan: Rp {{ number_format($pendapatanPreview->total_pendapatan, 0, ',', '.') }}
        </p>

        @if ($pendapatanPreview->total_hutang > 0 || $pendapatanPreview->total_cashbon > 0)
            <table>
                <thead>
                    <tr>
                        <th>Jenis Potongan</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($pendapatanPreview->total_hutang > 0)
                        <tr>
                            <td>Potongan Hutang</td>
                            <td>Rp {{ number_format($pendapatanPreview->total_hutang, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    @if ($pendapatanPreview->total_cashbon > 0)
                        <tr>
                            <td>Potongan Cashbon</td>
                            <td>Rp {{ number_format($pendapatanPreview->total_cashbon, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <p class="total">Total Potongan: Rp
                {{ number_format($pendapatanPreview->total_hutang + $pendapatanPreview->total_cashbon, 0, ',', '.') }}
            </p>
        @endif

        <p class="final-total">Total Transfer: Rp {{ number_format($pendapatanPreview->total_transfer, 0, ',', '.') }}
        </p>

        <p style="text-align: center; color: #6b7280; font-size: 12px; margin-top: 24px;">
            <em>Dokumen ini adalah preview invoice sebelum pembayaran</em>
        </p>

    </div>
</body>

</html>
