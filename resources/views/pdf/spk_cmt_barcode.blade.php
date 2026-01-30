<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
            padding: 0;
        }
        .page {
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
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
@endphp

@foreach ($qrItems as $item)

@php
    $qrBase64 = $dns2d->getBarcodePNG(
        $item['qr_value'],
        'QRCODE',
        6,
        6
    );
@endphp

<div class="page">

    <div class="header-title">
        SPK CMT BARCODE
    </div>

    <table>
        <tr>
            <td>
                <img class="qr-small" src="data:image/png;base64,{{ $qrBase64 }}">
            </td>

            <td>
                <div class="sku-text">{{ $item['sku_display'] ?? $item['sku'] }}</div>
                <div class="seri-text">Kode Seri: {{ $item['kode_seri'] }}</div>
                <div style="font-size:8pt">{{ $item['qr_value'] }}</div>
            </td>

            <td>
                {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>

</div>

@endforeach


</body>


</html>
