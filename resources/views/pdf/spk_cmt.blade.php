<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK CMT PDF</title>
    <style>
        @page {
    size: A4; /* Atur ukuran kertas A4, atau ganti sesuai kebutuhan */
    margin: 8mm; /* Atur margin sesuai dengan kebutuhan */
}
        /* Global Styles */
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
       

        /* Table Styles */
        table {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-top :-10px;
        }

        table th, table td {
            padding: 10px;
            text-align: left;
            font-size: 12px;
            overflow: hidden; /* Menghindari teks meluber */
            text-overflow: ellipsis; /* Memberikan efek ... jika teks terlalu panjang */
            word-wrap: break-word; /* Memecah kata panjang */
            
        }

        table th {
            background-color:rgb(141, 206, 226);
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
            
        }

        table tr:nth-child(even) {
            background-color: #F7F9F9;
        }

        table tr:hover {
            background-color: #E8F6F3;
            transition: background-color 0.3s ease;
        }

        img {
            width: 140px;
            height: 150px;
            display: block;
            margin: 10px 0;
            border-radius: 10px;
            margin-left:50px;
        }

        .details {
            margin-top: -5px;
            background-color:rgb(245, 247, 248);
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
            width: 97%;
        }

        .details h3 {
            margin-top:3px;
            margin-bottom: 2px;
            font-size: 12px;
            color:rgb(120, 203, 218);
            text-transform: uppercase;
        }
        .details ol {
            margin: 0;
            padding-left: 20px; /* Memberi ruang untuk angka */
            list-style-position: outside; /* Angka berada di luar blok teks */
        }

        .details ol li {
            margin-bottom: 10px; /* Memberi jarak antar item */
            text-align: justify; /* Membuat teks lebih rapi jika multiline */
            line-height: 1.4; /* Menambah kenyamanan pembacaan */
            font-size: 12px;
        }

        .details ol li::marker {
            font-weight: bold; /* Mempertegas angka */
        }
        .card-container {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Dua kolom dengan lebar sama */
            gap: 20px; /* Jarak antar card */
        }

        .additional-card {
            margin-top: 10px;
            background-color:rgb(245, 247, 248);
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
            width: 58%;
            margin-left: 235px;
            margin-top: -170px;
        }
        .additional-card h3 {
            margin-top:3px;
            margin-bottom: 2px;
            font-size: 12px;
            color:rgb(120, 178, 218);
            text-transform: uppercase;
        }
        .additional-card p {
            font-size: 11px;
            margin-bottom: -20px;
            font-weight: '400';
            letter-spacing: 0.5px; /* Atur jarak antar huruf, misalnya 0.5px */
            line-height: 1.6; /* Tambahkan line height agar lebih nyaman dibaca */
        }
        /* Signature Section Styles */
        .signature-table {
            width: 100%;
            margin-top: 15px;
            text-align: center;
            background-color:#F7F9F9;
            border-collapse: separate;
            border-spacing: 10px;
        }

        .signature-table td {
            padding: 10px;
            vertical-align: top;
            text-align: center;
        }

        .signature-space {
            height: 50px; /* Placeholder for signature */
            border-bottom: 2px dashed #34495E;
            margin: 10px 0;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
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
            border-spacing: 7px; /* Kurangi jarak antar sel */
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
            text-align: justify; /* Membuat teks lebih rapi jika multiline */
            line-height: 1.1; /* Menambah kenyamanan pembacaan */
            font-size: 14px;
        }


       
    </style>
</head>
<body>

    <h1>SPK CMT ILOOK</h1>
<table class="header-table">
    <tr>
        <td>
            <p><strong>Nomor Spk &nbsp;&nbsp;&nbsp;&nbsp; &nbsp; &nbsp;  :</strong><strong> {{ $spk->id_spk }}</strong></p>
            <p><strong>Nama Produk  &nbsp;  &nbsp; :</strong> {{ $spk->nama_produk }}</p>
            <p><strong>Nama Penjahit &nbsp;  :</strong> {{ $spk->penjahit->nama_penjahit }}</p>
       
      
            <p><strong>Tanggal SPK   &nbsp;&nbsp;&nbsp;&nbsp; :</strong> {{ \Carbon\Carbon::parse($spk->tgl_spk)->format('d M Y') }}</p>
            <p><strong>Deadline &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; &nbsp; &nbsp;&nbsp;&nbsp; :</strong> {{ \Carbon\Carbon::parse($spk->deadline)->format('d M Y') }}</p>
            <p><strong>Tanggal Ambil &nbsp; :</strong> {{ \Carbon\Carbon::parse($spk->tanggal_ambil)->format('d M Y') }} </p>
        
         <td>
         <td style="text-align: right; vertical-align: top;">
            @if($spk->gambar_produk)
                <img src="{{ public_path('storage/' . $spk->gambar_produk) }}" alt="Gambar Produk">
            @else
                <p>N/A</p>
            @endif
        </td>
                
    </tr>
</table>



    <table>
        <thead>
            <tr>
                <th>Field</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Nomor Seri</strong></td>
                <td>{{ $spk->nomor_seri ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Keterangan</strong></td>
                <td>{{ $spk->keterangan ?? 'N/A' }}</td>
            </tr>
        
           
            <tr>
                <td><strong>Markeran</strong></td>
                <td>{{ $spk->markeran ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Aksesoris</strong></td>
                <td>{{ $spk->aksesoris ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Handtag</strong></td>
                <td>{{ $spk->handtag ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Merek</strong></td>
                <td>{{ $spk->merek ?? 'N/A' }}</td>
            </tr>
            <tr>
            <td><strong>Warna</strong></td>
            <td>
            @if($spk->warna->isEmpty())
                N/A
            @else
                {{ $spk->warna->map(fn($warna) => $warna->nama_warna . ' (' . $warna->qty . ')')->join(', ') }}
            @endif
        </td>

            </tr>
            <tr>
                <td><strong>Jumlah</strong></td>
                <td>{{ $spk->jumlah_produk ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Catatan</strong></td>
                <td>{{ $spk->catatan ?? 'N/A' }}</td>
            </tr>
            

            
        </tbody>
    </table>
    <div class="card-container">
    <div class="details">
    <h3>Note Lainnya</h3>
    <ol>
            <li>Sampel asli tidak boleh hilang, jika hilang CMT wajib mengganti kerugian sebesar RP. 500.00,- (Lima Ratur Ribu). Untuk pengembalian sampel yaitu di hari pertama pengiriman dan diserahkan kepada penerima kerjaan CMT. Jika pengiriman sampel di hari pertama pengiriman tidak dilakukan, maka secara otomatis akan dipotong sebesar Rp. 500.000,- (Lima Ratus Rupiah). Menandatangani berarti CMT setuju dengan semua ketentuan yang berlaku!</li>
            <li>Setelah ambil SPK batas laporan 2-3 hari, jika tidak ada maka kami anggap komplit (tidak ada masalah). Jika overtime (melebihi batas kirim) yang tidak jelas, maka langsung potong claim.</li>
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