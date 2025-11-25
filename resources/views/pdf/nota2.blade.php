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
            font-weight: 500;
        }
        
        th {
            background-color: #e5e7eb;
            color: #374151;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            font-weight: 600;
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
            <p>11 Mei 2025</p>
        </div>
        <div class="info-container">
        <div class="info">
            <p>Penjahit: <span>Paman Kusu</span></p>
            <p>Alamat: <span>Jl. Pekapuran</span></p>
        </div>
        <div class="info">
            <p>Penjahit: <span>Paman Kusu</span></p>
            <p>Alamat: <span>Jl. Pekapuran</span></p>
        </div>
    </div>

        <table>
            <thead>
                <tr>
                    <th>ID SPK</th>
                    <th>Nama Produk</th>
                    <th>Tanggal Pengiriman</th>
                    <th>Total Barang</th>
                    <th>Harga Barang</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>jsdfksdhg </td>
                    <td>Set Lesti</td>
                    <td>29 Januari 2025</td>
                    <td>30</td>
                    <td>Rp 60.000</td>
                    <td>Rp 1.800.000</td>
                </tr>
                <tr>
                    <td>124</td>
                    <td>Set Bela</td>
                    <td>30 Januari 2025</td>
                    <td>30</td>
                    <td>Rp 50.000</td>
                    <td>Rp 1.500.000</td>
                </tr>
            </tbody>
        </table>

        <p class="total">Total Pendapatan: Rp 3.300.000</p>

        <table>
            <thead>
                <tr>
                    <th>Jenis</th>
                    <th>ID SPK</th>
                    <th>Total Barang</th>
                    <th>Total Harga</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Claim</td>
                    <td>123</td>
                    <td>120</td>
                    <td>Rp 1.120.000</td>
                </tr>
                <tr>
                    <td>Refund Claim</td>
                    <td>124</td>
                    <td>120</td>
                    <td>Rp 1.120.000</td>
                </tr>
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
                <tr>
                    <td>Cashbon</td>
                    <td>Rp 500.000</td>
                </tr>
                <tr>
                    <td>Hutang</td>
                    <td>Rp 500.000</td>
                </tr>
                <tr>
                    <td>Handtag</td>
                    <td>Rp 100.000</td>
                </tr>
                <tr>
                    <td>Transportasi</td>
                    <td>Rp 400.000</td>
                </tr>
            </tbody>
        </table>

        <p class="total">Total Potongan: Rp 1.500.000</p>

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
                    <td>Rp 3.300.000</td>
                    <td>Rp 1.120.000</td>
                    <td>Rp 1.120.000</td>
                    <td>Rp 1.500.000</td>
                </tr>
            </tbody>
        </table>

        <p class="final-total">Total Transfer: Rp 1.800.000</p>
    </div>
</body>
</html>
