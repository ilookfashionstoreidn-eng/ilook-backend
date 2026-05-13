<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bahan List</title>
    <style>
        @page {
            margin: 7mm 8mm;
        }

        body {
            margin: 0;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }

        .header {
            width: 100%;
            margin-bottom: 22px;
        }

        .name-label {
            margin-left: 48px;
            font-size: 10px;
            font-weight: 400;
            text-transform: uppercase;
        }

        .material-name {
            margin: 12px 0 0 48px;
            font-size: 14px;
            line-height: 1.2;
            font-weight: 800;
            text-transform: uppercase;
        }

        .printed-at {
            text-align: right;
            padding-top: 13px;
            font-size: 15px;
            font-weight: 800;
            white-space: nowrap;
        }

        .layout {
            width: 100%;
            border-collapse: collapse;
        }

        .table-cell {
            width: 405px;
            vertical-align: top;
        }

        .spacer-cell {
            width: 82px;
        }

        .image-cell {
            width: 234px;
            vertical-align: top;
            padding-top: 26px;
        }

        .stock-table {
            width: 405px;
            table-layout: fixed;
            border-collapse: collapse;
            border-spacing: 0;
            font-size: 10px;
            line-height: 1.1;
        }

        .stock-table th,
        .stock-table td {
            border: 1px solid #111;
            padding: 2px 4px;
            color: #000;
            font-weight: 700;
            vertical-align: middle;
        }

        .stock-table thead th {
            height: 60px;
            background: #f7dfc3;
            text-align: center;
            text-transform: uppercase;
            white-space: normal;
        }

        .stock-table tbody td {
            height: 15px;
            background: #fff;
        }

        .stock-table .grand-head,
        .stock-table tbody td.grand-cell,
        .stock-table tfoot td.grand-cell {
            background: #d8eaf8;
        }

        .stock-table .no-cell {
            text-align: center;
            font-weight: 400;
        }

        .stock-table .warna-cell {
            text-align: left;
            text-transform: uppercase;
        }

        .stock-table .number-cell {
            text-align: right;
            white-space: nowrap;
        }

        .stock-table tfoot td {
            height: 24px;
            background: #f7c99c;
            text-align: right;
            font-size: 10px;
        }

        .stock-table tfoot .total-label {
            text-align: center;
        }

        .preview-image {
            display: block;
            width: 234px;
            max-height: 314px;
            object-fit: contain;
        }

        .image-empty {
            width: 232px;
            height: 180px;
            border: 1px dashed #999;
            color: #666;
            text-align: center;
            line-height: 180px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="name-label">NAMA BAHAN</div>
                <div class="material-name">{{ $group['group_bahan'] ?? '-' }}</div>
            </td>
            <td class="printed-at">{{ $printedAt }}</td>
        </tr>
    </table>

    <table class="layout">
        <tr>
            <td class="table-cell">
                <table class="stock-table">
                    <colgroup>
                        <col style="width: 45px;">
                        <col style="width: 119px;">
                        <col style="width: 80px;">
                        <col style="width: 80px;">
                        <col style="width: 80px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>WARNA</th>
                            <th>STOK GUDANG - TOTAL</th>
                            <th>DIPESAN - TOTAL</th>
                            <th class="grand-head">GRAND TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="no-cell">{{ $row['no'] }}</td>
                                <td class="warna-cell">{{ $row['warna'] }}</td>
                                <td class="number-cell">{{ $row['stok_gudang'] }}</td>
                                <td class="number-cell">{{ $row['dipesan'] }}</td>
                                <td class="number-cell grand-cell">{{ $row['grand_total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="total-label">TOTAL</td>
                            <td>{{ $totals['stok_gudang'] }}</td>
                            <td>{{ $totals['dipesan'] }}</td>
                            <td class="grand-cell">{{ $totals['grand_total'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>
            <td class="spacer-cell"></td>
            <td class="image-cell">
                @if ($imageDataUri)
                    <img class="preview-image" src="{{ $imageDataUri }}" alt="Gambar bahan">
                @else
                    <div class="image-empty">Tidak ada gambar</div>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
