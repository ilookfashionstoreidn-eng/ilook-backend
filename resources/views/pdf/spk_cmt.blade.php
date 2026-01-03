<!DOCTYPE html>
<html lang="en">

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
            font-family: 'Roboto', sans-serif;
            color: #2C3E50;
            background-color: #ECF0F1;
            margin: 0;
            padding: 25px;
        }

        h1 {
            text-align: center;
            color: rgb(64, 178, 212);
            margin-bottom: 10px;
            margin-top: 5px;
            font-size: 28px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        table {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-top: -10px;
        }

        table th,
        table td {
            padding: 10px;
            text-align: left;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            word-wrap: break-word;
        }

        table th {
            background-color: rgb(141, 206, 226);
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        table tr:nth-child(even) {
            background-color: #F7F9F9;
        }

        img {
            width: 140px;
            height: 150px;
            display: block;
            margin: 10px 0;
            border-radius: 10px;
            margin-left: 50px;
        }

        .details {
            margin-top: -5px;
            background-color: rgb(245, 247, 248);
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
            width: 97%;
        }

        .details h3 {
            margin-top: 3px;
            margin-bottom: 2px;
            font-size: 12px;
            color: rgb(120, 203, 218);
            text-transform: uppercase;
        }

        .details ol {
            margin: 0;
            padding-left: 20px;
            list-style-position: outside;
        }

        .details ol li {
            margin-bottom: 10px;
            text-align: justify;
            line-height: 1.4;
            font-size: 12px;
        }

        .details ol li::marker {
            font-weight: bold;
        }

        .card-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .additional-card {
            margin-top: 10px;
            background-color: rgb(245, 247, 248);
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
            width: 58%;
            margin-left: 235px;
            margin-top: -170px;
        }

        .additional-card h3 {
            margin-top: 3px;
            margin-bottom: 2px;
            font-size: 12px;
            color: rgb(120, 178, 218);
            text-transform: uppercase;
        }

        .additional-card p {
            font-size: 11px;
            margin-bottom: -20px;
            font-weight: 400;
            letter-spacing: 0.5px;
            line-height: 1.6;
        }

        .signature-table {
            width: 100%;
            margin-top: 15px;
            text-align: center;
            background-color: #F7F9F9;
            border-collapse: separate;
            border-spacing: 10px;
        }

        .signature-table td {
            padding: 10px;
            vertical-align: top;
            text-align: center;
        }

        .signature-space {
            height: 50px;
            border-bottom: 2px dashed #34495E;
            margin: 10px auto;
            width: 80%;
        }

        .signature-name {
            margin-top: 10px;
            font-size: 13px;
            font-weight: bold;
            color: #34495E;
        }

        .header-table {
            width: 100%;
            margin-top: 5px;
            text-align: left;
            background-color: rgb(141, 206, 226);
            border-collapse: separate;
            border-spacing: 7px;
        }

        .header-table td {
            padding: 3px 10px;
            vertical-align: top;
            text-align: left;
            color: #F4F6F7;
            font-size: 14px;
            font-family: 'Arial', sans-serif;
        }

        .header-table p {
            text-align: justify;
            line-height: 1.1;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <h1>SPK CMT ILOOK</h1>

    {{-- Ambil data produk dari relasi --}}
    @php
        $produk = null;
        $nomorSeri = null;
        if ($spk->source_type === 'cutting' && $spk->spkCuttingDistribusi) {
            $produk = $spk->spkCuttingDistribusi->spkCutting->produk ?? null;
            $nomorSeri = $spk->spkCuttingDistribusi->kode_seri;
        } elseif ($spk->source_type === 'jasa' && $spk->spkJasa?->spkCuttingDistribusi) {
            $produk = $spk->spkJasa->spkCuttingDistribusi->spkCutting->produk ?? null;
            $nomorSeri = $spk->spkJasa->spkCuttingDistribusi->kode_seri;
        }
    @endphp

    <table class="header-table">
        <tr>
            <td>
                <p><strong>Nomor SPK &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong>
                    {{ $spk->id_spk }}</p>
                <p><strong>Nama Produk &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong> {{ $produk?->nama_produk ?? '–' }}
                </p>
                <p><strong>Nama Penjahit &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong>
                    {{ $spk->penjahit?->nama_penjahit ?? '–' }}</p>
                <p><strong>Tanggal SPK &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong>
                    {{ \Carbon\Carbon::parse($spk->created_at)->format('d M Y') }}</p>
                <p><strong>Deadline
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong>
                    {{ \Carbon\Carbon::parse($spk->deadline)->format('d M Y') }}</p>
                {{-- Tanggal Ambil tidak ada di model, jadi tampilkan status saja --}}
                <p><strong>Status
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</strong>
                    {{ ucfirst(str_replace('_', ' ', $spk->status)) }}</p>
            </td>
            <td style="text-align: right; vertical-align: top;">
                @if ($produk?->gambar_produk)
                    <img src="{{ public_path('storage/' . $produk->gambar_produk) }}" alt="Gambar Produk">
                @else
                    <p style="margin-top:60px;">–</p>
                @endif
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Color</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @php
                // Siapkan data informasi lain (selain warna) dengan format: field_name => value
                $otherFields = [
                    ['label' => 'Nomor Seri', 'value' => $nomorSeri ?? '–'],
                    ['label' => 'Keterangan', 'value' => $spk->keterangan ?? '–'],
                    ['label' => 'Markeran', 'value' => $spk->markeran ?? '–'],
                    ['label' => 'Aksesoris', 'value' => $spk->aksesoris ?? '–'],
                    ['label' => 'Handtag', 'value' => $spk->handtag ?? '–'],
                    ['label' => 'Merek', 'value' => $spk->merek ?? '–'],
                    ['label' => 'Jumlah', 'value' => $spk->jumlah_produk ?? ($spk->warna->sum('qty') ?? '–')],
                    ['label' => 'Catatan', 'value' => $spk->catatan ?? '–'],
                ];

                // Jika tidak ada warna, tampilkan semua field lain seperti biasa
                if ($spk->warna->isEmpty()) {
                    foreach ($otherFields as $field) {
                        echo "<tr><td><strong>{$field['label']}</strong></td><td>{$field['value']}</td></tr>";
                    }
                } else {
                    // Jika ada warna, tampilkan warna di Field dan info lain di Value (di-rotate per baris)
                    $warnaList = $spk->warna->toArray();
                    $maxRows = max(count($warnaList), count($otherFields));

                    for ($i = 0; $i < $maxRows; $i++) {
                        $warnaText = '';
                        $valueText = '';

                        // Field: Warna dengan qty
                        if (isset($warnaList[$i])) {
                            $warnaText = "Warna {$warnaList[$i]['nama_warna']} {$warnaList[$i]['qty']} pcs";
                        }

                        // Value: Info lain yang di-rotate (dengan label dan value)
                        if (isset($otherFields[$i])) {
                            $valueText = "{$otherFields[$i]['label']}: {$otherFields[$i]['value']}";
                        }

                        // Tampilkan baris jika ada warna atau value
                        if ($warnaText || $valueText) {
                            echo "<tr><td><strong>{$warnaText}</strong></td><td>{$valueText}</td></tr>";
                        }
                    }
                }
            @endphp
        </tbody>
    </table>

    <div class="card-container">
        <div class="details">
            <h3>Note Lainnya</h3>
            <ol>
                <li>Sampel asli tidak boleh hilang, jika hilang CMT wajib mengganti kerugian sebesar RP. 500.000,- (Lima
                    Ratus Ribu Rupiah). Untuk pengembalian sampel yaitu di hari pertama pengiriman dan diserahkan kepada
                    penerima kerjaan CMT. Jika pengiriman sampel di hari pertama pengiriman tidak dilakukan, maka secara
                    otomatis akan dipotong sebesar Rp. 500.000,-. Menandatangani berarti CMT setuju dengan semua
                    ketentuan yang berlaku!</li>
                <li>Setelah ambil SPK batas laporan 2-3 hari, jika tidak ada maka kami anggap komplit (tidak ada
                    masalah). Jika overtime (melebihi batas kirim) yang tidak jelas, maka langsung potong claim.</li>
            </ol>
        </div>
    </div>

    <table class="signature-table">
        <tr>
            <td>
                <p><strong>Dibuat Oleh:</strong></p>
                <div class="signature-space"></div>
                <p class="signature-name">(Nama)</p>
            </td>
            <td>
                <p><strong>Mengetahui:</strong></p>
                <div class="signature-space"></div>
                <p class="signature-name">(Nama)</p>
            </td>
            <td>
                <p><strong>Diterima Oleh:</strong></p>
                <div class="signature-space"></div>
                <p class="signature-name">(Nama)</p>
            </td>
        </tr>
    </table>
</body>

</html>
