<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Pendapatan</title>
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
            border-bottom: 1px solid #ddd;
            padding-bottom: 16px;
        }
        h2 {
            font-size: 20px;
            color: #374151;
        }
        .info-container {
            display: flex;
            justify-content: space-between;
            gap: 20px; /* Beri jarak antar kolom */
        }

        .info {
            flex: 1; /* Membagi area jadi 2 kolom */
        }

        .info p {
            font-size: 16px;
        }

        .info span {
            font-weight: normal;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center; 
            font-weight: normal;
        }
        
        th {
            background-color: #e5e7eb;
            color: #374151;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            font-weight: bold;
        }
        .total {
            text-align: right;
            font-size: 18px;
            font-weight: bold;
            margin-top: 16px;
        }
        .final-total {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="title">
            <h2>NOTA PENDAPATAN</h2>
            <p>{{ date_format(date_create($pendapatan->periode_awal), 'd M Y') }} -  {{ date_format(date_create($pendapatan->periode_akhir), 'd M Y') }}</p>
        </div>

        <div class="info-container">
    <div class="info">
        <p>Penjahit: <span>{{ $penjahit->nama_penjahit ?? 'Tidak Ada' }}</span></p>
        <p>Alamat: <span>{{ $penjahit->alamat ?? 'Tidak Ada' }}</span></p>
    </div>
    <div class="info">
        <p>Bank: <span>{{ $penjahit->bank ?? 'Tidak Ada' }}</span></p>
        <p>No. Rekening: <span>{{ $penjahit->no_rekening ?? 'Tidak Ada' }}</span></p>
    </div>
</div>


        <table>
            <thead>
                <tr>
                    <th>ID SPK</th>
                    <th>Nama Produk</th>
                    <th>Tanggal Pengiriman</th>
                    <th>Total Barang</th>
                    <th>Harga Jasa</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($pengiriman as $data)
            <tr>
                <td>{{ $data->id_spk }}</td>
                <td>{{ $data->nama_produk ?? 'N/A' }}</td>
                <td>{{ $data->tanggal_pengiriman }}</td>
                <td>{{ $data->total_barang_dikirim }}</td>
                <td>Rp {{ number_format($data->spk->harga_per_jasa ?? 0, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($data->total_bayar ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>

        </table>

        <p class="total">Total Pendapatan: Rp {{ number_format($pendapatan->total_pendapatan, 0, ',', '.') }}</p>

        <table>
            <thead>
                <tr>
                    <th>Jenis</th>
                    <th>ID SPK</th>
                    <th>Total </th> 
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

                @if ($pendapatan->handtag > 0)
                    <tr>
                        <td>Handtag</td>
                        <td>Rp {{ number_format($pendapatan->handtag, 0, ',', '.') }}</td>
                    </tr>
                @endif

                @if ($pendapatan->transportasi > 0)
                    <tr>
                        <td>Transportasi</td>
                        <td>Rp {{ number_format($pendapatan->transportasi, 0, ',', '.') }}</td>
                    </tr>
                @endif
            </tbody>
        </table>


        <p class="total">Total Potongan: Rp {{ number_format(
            $pendapatan->total_cashbon + 
            $pendapatan->total_hutang + 
            $pendapatan->handtag + 
            $pendapatan->transportasi, 0, ',', '.') }}</p>

        <table>
            <thead>
                <tr>
                    <th>Total Pendapatan</th>
                    <th>Total Refund Claim</th>
                    <th>Total Claim</th>
                    <th>Total Potongan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Rp {{ number_format($pendapatan->total_pendapatan, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($pendapatan->total_refund_claim, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($pendapatan->total_claim, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format(
                        $pendapatan->total_cashbon + 
                        $pendapatan->total_hutang + 
                        $pendapatan->handtag + 
                        $pendapatan->transportasi, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <p class="final-total">Total Transfer: Rp {{ number_format($pendapatan->total_transfer, 0, ',', '.') }}</p>

    </div>
</body>
</html>
