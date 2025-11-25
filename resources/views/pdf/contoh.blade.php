<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Pendapatan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto bg-white p-8 shadow-lg rounded-lg">
        <div class="text-center border-b pb-4">
            <h2 class="text-2xl font-semibold text-gray-700">NOTA PENDAPATAN</h2>
            <p class="text-gray-500">11 Mei 2025</p>
        </div>

        <div class="mt-4">
            <p class="text-lg font-semibold">Penjahit: <span class="font-normal">Paman Kusu</span></p>
            <p class="text-lg font-semibold">Alamat: <span class="font-normal">Jl. Pekapuran</span></p>
        </div>

        <div class="mt-6">
            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200 text-gray-700">
                        <th class="border p-2">ID SPK</th>
                        <th class="border p-2">Nama Produk</th>
                        <th class="border p-2">Tanggal Pengiriman</th>
                        <th class="border p-2">Total Barang</th>
                        <th class="border p-2">Harga Barang</th>
                        <th class="border p-2">Total</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600">
                    <tr class="border">
                        <td class="border p-2">123</td>
                        <td class="border p-2">Set Lesti</td>
                        <td class="border p-2">29 Januari 2025</td>
                        <td class="border p-2">30</td>
                        <td class="border p-2">Rp 60.000</td>
                        <td class="border p-2">Rp 1.800.000</td>
                    </tr>
                    <tr class="border">
                        <td class="border p-2">124</td>
                        <td class="border p-2">Set Bela</td>
                        <td class="border p-2">30 Januari 2025</td>
                        <td class="border p-2">30</td>
                        <td class="border p-2">Rp 50.000</td>
                        <td class="border p-2">Rp 1.500.000</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-right text-lg font-semibold">
            <p>Total Pendapatan: <span class="text-gray-700">Rp 3.300.000</span></p>
        </div>

        <div class="mt-6">
            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200 text-gray-700">
                        <th class="border p-2">Jenis</th>
                        <th class="border p-2">ID SPK</th>
                        <th class="border p-2">Total Barang</th>
                        <th class="border p-2">Total Harga</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600">
                    <tr class="border">
                        <td class="border p-2">Claim</td>
                        <td class="border p-2">123</td>
                        <td class="border p-2">120</td>
                        <td class="border p-2">Rp 1.120.000</td>
                    </tr>
                    <tr class="border">
                        <td class="border p-2">Refund Claim</td>
                        <td class="border p-2">124</td>
                        <td class="border p-2">120</td>
                        <td class="border p-2">Rp 1.120.000</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200 text-gray-700">
                        <th class="border p-2">Potongan</th>
                        <th class="border p-2">Total Harga</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600">
                    <tr class="border">
                        <td class="border p-2">Cashbon</td>
                        <td class="border p-2">Rp 500.000</td>
                    </tr>
                    <tr class="border">
                        <td class="border p-2">Hutang</td>
                        <td class="border p-2">Rp 500.000</td>
                    </tr>
                    <tr class="border">
                        <td class="border p-2">Handtag</td>
                        <td class="border p-2">Rp 100.000</td>
                    </tr>
                    <tr class="border">
                        <td class="border p-2">Transportasi</td>
                        <td class="border p-2">Rp 400.000</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-right text-lg font-semibold">
            <p>Total Potongan: <span class="text-gray-700">Rp 1.500.000</span></p>
        </div>

        <div class="mt-6">
            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200 text-gray-700">
                        <th class="border p-2">Total Pendapatan</th>
                        <th class="border p-2">Total Refund Claim</th>
                        <th class="border p-2">Total Claim</th>
                        <th class="border p-2">Total Potongan</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600">
                    <tr class="border">
                        <td class="border p-2">Rp 3.300.000</td>
                        <td class="border p-2">Rp 1.120.000</td>
                        <td class="border p-2">Rp 1.120.000</td>
                        <td class="border p-2">Rp 1.500.000</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-right text-xl font-bold text-gray-800">
            <p>Total Transfer: Rp 1.800.000</p>
        </div>

       
    </div>
</body>
</html>
