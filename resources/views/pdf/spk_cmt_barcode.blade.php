<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: 100mm 60mm; /* lebar x tinggi */
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
            height: 70mm;
            justify-content: center;
            align-items: center;
            text-align: center;
            margin-top: 2mm;
            font-family: Arial, Helvetica, sans-serif;
        }

        .sku-text {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 3mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            height: 55mm;
        }

        td {
           
            padding: 5px;
            vertical-align: middle;
            text-align: center;
            font-weight: bold;
             justify-content: center;
            
        }

        .qr-small {
            width: 35mm;
            height: 35mm;
            padding: 5px !important;
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
    <table>
        <tr>
            <td>
                <img class="qr-small" src="data:image/png;base64,{{ $qrBase64 }}">
                <div class="sku-text">
                    {{ $item['kode_seri'] }} {{ $item['qr_value'] }}
                </div>
            </td>
        </tr>
    </table>
</div>

@endforeach

</body>
</html>
