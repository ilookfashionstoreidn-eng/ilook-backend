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
            padding: `0;
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
            width: 97%;

            border-collapse: collapse;
            font-size: 9pt;
            height: 40mm;
            justify-content: center;
        }

        td {
            border: 1px solid #000;
            padding: 2px;
            vertical-align: middle;
            text-align: center;
            font-weight: bold;
        }

        .qr-small {
            width: 25mm;
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

                        <td>
                            {{ optional(optional($pembelianBahan->bahan))->nama_bahan ?? '-' }} <br>
                            {{ optional($rol->warna)->warna ?? '-' }} <br>
                            Pabrik: {{ $pembelianBahan->pabrik->nama_pabrik }}<br>
                            Gramasi: {{ $pembelianBahan->gramasi }}<br>
                            Lebar : {{ $pembelianBahan->lebar_kain }}<br>
                            Berat: {{ number_format($rol->berat ?? 0, 2) }}
                        </td>

                        <td>
                            Tanggal<br>
                            Kirim<br>
                            {{ \Carbon\Carbon::parse($pembelianBahan->tanggal_kirim)->format('d/m/Y') }}
                        </td>
                    </tr>

                </table>
            </div>

        </div>
    @endforeach

</body>

</html>
