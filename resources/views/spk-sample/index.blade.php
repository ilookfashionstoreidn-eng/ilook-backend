<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK Sample</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f9fc;
            color: #1f2937;
        }

        .container {
            max-width: 1100px;
            margin: 32px auto;
            padding: 0 16px;
        }

        h1 {
            margin: 0 0 16px;
            font-size: 28px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #f1f5f9;
            font-weight: 700;
            white-space: nowrap;
        }

        .status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1e3a8a;
            font-size: 12px;
            font-weight: 700;
        }

        .photo {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .muted {
            color: #64748b;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>SPK Sample</h1>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr>
                <th>Nama sample</th>
                <th>Kategori Sample</th>
                <th>Detail</th>
                <th>Status SPK</th>
                <th>Keterangan sample</th>
                <th>Foto</th>
            </tr>
            </thead>
            <tbody>
            @forelse($samples as $sample)
                <tr>
                    <td>{{ $sample->nama_sample }}</td>
                    <td>{{ $sample->kategori_sample }}</td>
                    <td>{{ $sample->detail ?: '-' }}</td>
                    <td><span class="status">{{ $sample->status_spk }}</span></td>
                    <td>{{ $sample->keterangan_sample ?: '-' }}</td>
                    <td>
                        @if($sample->foto)
                            <img class="photo" src="{{ asset('storage/' . $sample->foto) }}" alt="Foto {{ $sample->nama_sample }}">
                        @else
                            <span class="muted">Tidak ada foto</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Belum ada data SPK Sample.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
