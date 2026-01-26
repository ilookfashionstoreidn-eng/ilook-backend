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
            font-family: Arial, Helvetica, sans-serif;
        }

        .header-title {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 3mm;
        }

        .sku-text {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 1mm;
        }

        .seri-text {
            font-size: 9pt;
            color: #333;
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

    @php
        $dns2d = new \Milon\Barcode\DNS2D();
        $qrBase64 = $dns2d->getBarcodePNG($barcodeValue, 'QRCODE', 6, 6);
    @endphp

    <div class="page">

        <div class="header-title">
            SPK CMT BARCODE
        </div>

        <div class="table-wrapper">
            <table>
                <tr>
                    <!-- QR -->
                    <td>
                        <img class="qr-small" src="data:image/png;base64,{{ $qrBase64 }}">
                    </td>

                    <!-- SKU + KODE SERI -->
                    <td>
                        <div class="sku-text">
                            SKU: {{ $sku }}
                        </div>
                        <div class="seri-text">
                            Kode Seri: {{ $kode_seri }}
                        </div>
                        <br>
                        <div>
                            Barcode Value:
                        </div>
                        <div style="font-size: 8pt; font-weight: normal;">
                            {{ $barcodeValue }}
                        </div>
                    </td>

                    <!-- TANGGAL CETAK -->
                    <td>
                        Tanggal<br>
                        Cetak<br>
                        {{ \Carbon\Carbon::now()->format('d/m/Y') }}
                    </td>
                </tr>
            </table>
        </div>

    </div>

</body>

</html>
