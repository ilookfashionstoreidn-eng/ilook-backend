<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
            padding: 0;
        }


        body {
            margin: 0;
            padding: 0;
            width: 100mm;
            height: 50mm;
            justify-content: center;
            align-items: center;
            text-align: center;
            margin-top: 9mm;
           
        }

        .nama-bahan {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 2mm;
            color: #000;
        }

        .warna {
            font-size: 10pt;
            color: #333;
        }
        .header-title {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 3mm;
        }

        .table-wrapper {
            width: 100%;
            display: block;
            text-align: center;
        }

        .table-wrapper table {
            margin-left: auto;
            margin-right: auto;
        }

        table {
                width: 95%;
               
                border-collapse: collapse;
                font-size: 9pt;
                height : 40mm;
                justify-content: center;
            }

            td {
                border: 1px solid #000;
                padding: 2px;
                vertical-align: middle;
                text-align: center;
            }

            .qr-small {
                width: 23mm; 
                height: 25mm;
                padding: 8px !important;
            }


    </style>
</head>

<body>

    @foreach ($barcodes as $rol)
        <div class="page">
            @php
                $dns2d = new \Milon\Barcode\DNS2D();
                $qrBase64 = $dns2d->getBarcodePNG($rol->barcode, 'QRCODE', 6, 6);
            @endphp


          <div class="table-wrapper">
             <table>
                <tr>
                    <td>
                    <img class="qr-small" src="data:image/png;base64,{{ $qrBase64 }}">
                    </td>
                    <td>{{ optional(optional($pembelianBahan->bahan))->nama_bahan ?? '-' }} <br>
                     {{ optional($rol->warna)->warna ?? '-' }}</td>
                     
                    <td> GRAMASI: {{ $pembelianBahan->gramasi }}<br>
                    LEBAR KAIN: {{ $pembelianBahan->lebar_kain }}<br>
                    BERAT: {{ number_format($rol->berat ?? 0, 2) }}</td>
                    <td> TANGGAL<br>
                    KIRIM<br>
                    {{ \Carbon\Carbon::parse($pembelianBahan->tanggal_kirim)->format('d/m/Y') }}</td>
                </tr>
            </table>
        </div>

        </div>
    @endforeach

</body>

</html>