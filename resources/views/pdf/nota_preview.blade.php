<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Preview Pendapatan</title>
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
            border-bottom: 2px solid #0369a1;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        h2 {
            font-size: 24px;
            color: #0369a1;
            margin: 0;
        }

        .preview-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
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
            padding: 3px;
            text-align: center;
            font-size: 10px;
        }

        th {
            background-color: #0369a1;
            color: white;
            border: 1px solid #0369a1;
            padding: 5px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
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
            color: #0369a1;
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

        .status-badge {
            color: #dc2626;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="title">
            <h2>INVOICE PREVIEW PENDAPATAN <span class="preview-badge">PREVIEW</span></h2>
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
                <p><strong>Penjahit:</strong> {{ $penjahit->nama_penjahit ?? 'Tidak Ada' }}</p>
                <p><strong>Alamat:</strong> {{ $penjahit->alamat ?? 'Tidak Ada' }}</p>
                <p><strong>Status:</strong> <span class="status-badge">Belum Dibayar</span></p>
            </div>
            <div class="info">
                @if ($penjahit && $penjahit->bank)
                    <p><strong>Bank:</strong> {{ $penjahit->bank }}</p>
                @endif
                @if ($penjahit && $penjahit->no_rekening)
                    <p><strong>No. Rekening:</strong> {{ $penjahit->no_rekening }}</p>
                @endif
            </div>
        </div>

        @if ($pengiriman && $pengiriman->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>ID SPK</th>
                        <th>Nama Produk</th>
                        <th>Tanggal Kirim</th>
                        <th>Total Barang</th>
                        <th>Harga Jasa</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pengiriman as $data)
                        @php
                            // Pastikan relasi spk dan produk ter-load
                            $data->loadMissing('spk.produk');

                            // Ambil nama produk dari relasi - gunakan optional() untuk safety
                            $namaProduk = optional(optional($data->spk)->produk)->nama_produk ?? 'N/A';
                        @endphp
                        <tr>
                            <td>{{ $data->id_spk }}</td>
                            <td>{{ $namaProduk }}</td>
                            <td>{{ date_format(date_create($data->tanggal_pengiriman), 'd M Y') }}</td>
                            <td>{{ $data->total_barang_dikirim ?? 0 }}</td>
                            <td>Rp {{ number_format($data->spk->harga_per_jasa ?? 0, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($data->total_bayar ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="total">Total Pendapatan: Rp {{ number_format($pendapatan->total_pendapatan, 0, ',', '.') }}</p>

            @if ($pengiriman->sum('claim') > 0 || $pengiriman->sum('refund_claim') > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th>ID SPK</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pengiriman as $data)
                            @if (!empty($data->claim) && $data->claim > 0)
                                <tr>
                                    <td>Claim</td>
                                    <td>{{ $data->id_spk }}</td>
                                    <td>Rp {{ number_format($data->claim, 0, ',', '.') }}</td>
                                </tr>
                            @endif
                            @if (!empty($data->refund_claim) && $data->refund_claim > 0)
                                <tr>
                                    <td>Refund Claim</td>
                                    <td>{{ $data->id_spk }}</td>
                                    <td>Rp {{ number_format($data->refund_claim, 0, ',', '.') }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($pendapatan->total_cashbon > 0 || $pendapatan->total_hutang > 0 || $pendapatan->potongan_aksesoris > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Potongan</th>
                            <th>Total Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($pendapatan->total_cashbon > 0)
                            <tr>
                                <td>Cashbon</td>
                                <td>Rp {{ number_format($pendapatan->total_cashbon, 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        @if ($pendapatan->total_hutang > 0)
                            <tr>
                                <td>Hutang</td>
                                <td>Rp {{ number_format($pendapatan->total_hutang, 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        @if (isset($pendapatan->potongan_aksesoris) && $pendapatan->potongan_aksesoris > 0)
                            <tr>
                                <td>Aksesoris</td>
                                <td>Rp {{ number_format($pendapatan->potongan_aksesoris, 0, ',', '.') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                <p class="total">Total Potongan: Rp
                    {{ number_format(
                        ($pendapatan->total_cashbon ?? 0) +
                            ($pendapatan->total_hutang ?? 0) +
                            (isset($pendapatan->potongan_aksesoris) ? $pendapatan->potongan_aksesoris : 0),
                        0,
                        ',',
                        '.',
                    ) }}
                </p>
            @endif

            <p class="final-total">Total Transfer: Rp {{ number_format($pendapatan->total_transfer, 0, ',', '.') }}</p>
        @else
            <p style="text-align: center; color: #6b7280; padding: 20px;">
                Tidak ada data pengiriman untuk periode ini
            </p>
        @endif

        <p style="text-align: center; color: #6b7280; font-size: 12px; margin-top: 24px;">
            <em>Dokumen ini adalah preview invoice sebelum pembayaran</em>
        </p>
    </div>
</body>

</html>
