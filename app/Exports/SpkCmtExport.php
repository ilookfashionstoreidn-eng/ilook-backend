<?php

namespace App\Exports;

use App\Models\SpkCmt;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SpkCmtExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $status = $this->filters['status'] ?? null;
        $idPenjahit = $this->filters['id_penjahit'] ?? null;
        $sourceType = $this->filters['source_type'] ?? null;
        $idProduk = $this->filters['id_produk'] ?? null;
        $kategoriProduk = $this->filters['kategori_produk'] ?? null;
        $sisaHari = $this->filters['sisa_hari'] ?? null;
        $deadlineStatus = $this->filters['deadline_status'] ?? null;
        $kirimMingguIni = $this->filters['kirim_minggu_ini'] ?? null;
        $startDate = $this->filters['start_date'] ?? null;
        $endDate = $this->filters['end_date'] ?? null;
        $sortBy = $this->filters['sortBy'] ?? 'created_at';
        $sortOrder = $this->filters['sortOrder'] ?? 'desc';

        $sortColumn = $sortBy === 'sisa_hari' ? 'deadline' : $sortBy;

        $query = SpkCmt::with([
            'warna',
            'pengiriman',
            'penjahit',
            'spkCuttingDistribusi.spkCutting.produk',
            'spkCuttingDistribusi.spkCutting.productList',
            'spkJasa.spkCuttingDistribusi.spkCutting.produk',
            'spkJasa.spkCuttingDistribusi.spkCutting.productList',
        ]);

        // Filter berdasarkan status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter berdasarkan penjahit
        if ($idPenjahit) {
            $query->where('id_penjahit', $idPenjahit);
        }

        // Filter berdasarkan tipe sumber
        if ($sourceType) {
            $query->where('source_type', $sourceType);
        }

        // Filter berdasarkan produk
        if ($idProduk) {
            $query->where(function ($subQ) use ($idProduk) {
                $subQ->where(function ($cuttingQ) use ($idProduk) {
                    $cuttingQ->where('source_type', 'cutting')
                        ->whereHas('spkCuttingDistribusi.detail', function ($detailQ) use ($idProduk) {
                            $detailQ->where('id_produk', $idProduk);
                        });
                })->orWhere(function ($jasaQ) use ($idProduk) {
                    $jasaQ->where('source_type', 'jasa')
                        ->whereHas('spkJasa.spkCuttingDistribusi.detail', function ($detailQ) use ($idProduk) {
                            $detailQ->where('id_produk', $idProduk);
                        });
                });
            });
        }

        // Filter berdasarkan kategori produk
        if ($kategoriProduk) {
            $query->where(function ($subQ) use ($kategoriProduk) {
                $subQ->where(function ($cuttingQ) use ($kategoriProduk) {
                    $cuttingQ->where('source_type', 'cutting')
                        ->whereHas('spkCuttingDistribusi.detail.produk', function ($produkQ) use ($kategoriProduk) {
                            $produkQ->where('kategori_produk', $kategoriProduk);
                        });
                })->orWhere(function ($jasaQ) use ($kategoriProduk) {
                    $jasaQ->where('source_type', 'jasa')
                        ->whereHas('spkJasa.spkCuttingDistribusi.detail.produk', function ($produkQ) use ($kategoriProduk) {
                            $produkQ->where('kategori_produk', $kategoriProduk);
                        });
                });
            });
        }

        // Filter berdasarkan sisa hari
        if ($sisaHari !== null) {
            if ($sisaHari === '0-3') {
                $query->whereRaw('DATEDIFF(deadline, CURDATE()) BETWEEN 0 AND 3');
            } elseif ($sisaHari === '4-7') {
                $query->whereRaw('DATEDIFF(deadline, CURDATE()) BETWEEN 4 AND 7');
            } elseif ($sisaHari === '8-14') {
                $query->whereRaw('DATEDIFF(deadline, CURDATE()) BETWEEN 8 AND 14');
            } elseif ($sisaHari === '15+') {
                $query->whereRaw('DATEDIFF(deadline, CURDATE()) >= 15');
            }
        }

        // Filter deadline status
        if ($deadlineStatus === 'masih_deadline') {
            $query->where('status', 'sudah_diambil')
                ->where(function ($subQ) {
                    $subQ->whereNull('deadline')
                        ->orWhere('deadline', '>=', Carbon::now()->startOfDay());
                });
        } elseif ($deadlineStatus === 'over_deadline') {
            $query->where('status', 'sudah_diambil')
                ->whereNotNull('deadline')
                ->where('deadline', '<', Carbon::now()->startOfDay());
        }

        // Filter kirim minggu ini
        if ($kirimMingguIni === 'true') {
            $mingguIniStart = Carbon::now()->startOfWeek();
            $mingguIniEnd = Carbon::now()->endOfWeek();
            $query->where('status', 'sudah_diambil')
                ->whereHas('pengiriman', function ($pengirimanQ) use ($mingguIniStart, $mingguIniEnd) {
                    $pengirimanQ->whereBetween('tanggal_pengiriman', [$mingguIniStart, $mingguIniEnd]);
                });
        }

        // Filter tanggal
        if ($startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        return $query->orderBy($sortColumn, $sortOrder)->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Tgl SPK',
            'Nama Penjahit',
            'Nomor Seri',
            'Nama Produk',
            'Qty SPK (Pcs)',
            'Total Kirim (Pcs)',
            'Sisa Kirim (Pcs)',
            'Deadline',
            'Harga Barang (Rp)',
            'Harga Jasa / Pcs (Rp)',
            'Total Nilai SPK (Rp)',
            'Status',
            'Keterangan',
        ];
    }

    public function map($item): array
    {
        $qtySpk = (int) $item->warna->sum('qty');
        $totalKirim = (int) $item->pengiriman->sum('total_barang_dikirim');
        $sisaKirim = $qtySpk - $totalKirim;

        $statusText = match ($item->status) {
            'belum_diambil' => 'Belum Diambil',
            'sudah_diambil' => 'Sudah Diambil',
            'pending' => 'Pending',
            'Completed', 'completed' => 'Completed',
            default => $item->status,
        };

        return [
            '', // No diisi otomatis di styles
            $item->created_at ? Carbon::parse($item->created_at)->format('d/m/Y') : '-',
            $item->penjahit->nama_penjahit ?? '-',
            $item->nomor_seri ?? '-',
            $item->nama_produk ?? '-',
            $qtySpk,
            $totalKirim,
            $sisaKirim,
            $item->deadline ? Carbon::parse($item->deadline)->format('d/m/Y') : '-',
            (float) ($item->harga_per_barang ?? 0),
            (float) ($item->harga_per_jasa ?? 0),
            (float) ($item->total_harga ?? 0),
            $statusText,
            $item->keterangan ?? '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 15,  // Tgl SPK
            'C' => 25,  // Nama Penjahit
            'D' => 18,  // Nomor Seri
            'E' => 30,  // Nama Produk
            'F' => 15,  // Qty SPK
            'G' => 18,  // Total Kirim
            'H' => 15,  // Sisa Kirim
            'I' => 15,  // Deadline
            'J' => 18,  // Harga Barang
            'K' => 20,  // Harga Jasa
            'L' => 22,  // Total Nilai SPK
            'M' => 15,  // Status
            'N' => 40,  // Keterangan
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style untuk header
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0487D8'], // Biru premium
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Auto number untuk kolom A dan border untuk baris data
        $highestRow = $sheet->getHighestRow();
        if ($highestRow >= 2) {
            for ($row = 2; $row <= $highestRow; $row++) {
                $sheet->setCellValue('A' . $row, $row - 1);
            }

            $sheet->getStyle('A2:N' . $highestRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Number formatting untuk kolom uang (J, K, L)
            $sheet->getStyle('J2:L' . $highestRow)
                ->getNumberFormat()
                ->setFormatCode('"Rp" #,##0');
        }

        // Alignments kolom
        $sheet->getStyle('A:A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No
        $sheet->getStyle('B:B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Tgl SPK
        $sheet->getStyle('D:D')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Nomor Seri
        $sheet->getStyle('F:H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);  // Qty, Kirim, Sisa
        $sheet->getStyle('I:I')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Deadline
        $sheet->getStyle('J:L')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);  // Uang
        $sheet->getStyle('M:M')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Status

        // Wrap text untuk keterangan
        $sheet->getStyle('N:N')->getAlignment()->setWrapText(true);

        // Row height untuk header
        $sheet->getRowDimension(1)->setRowHeight(28);

        return $sheet;
    }
}
