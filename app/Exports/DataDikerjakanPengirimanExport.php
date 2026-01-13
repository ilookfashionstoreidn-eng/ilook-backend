<?php

namespace App\Exports;

use App\Models\Penjahit;
use App\Models\SpkCmt;
use App\Models\Pengiriman;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class DataDikerjakanPengirimanExport implements FromArray, WithEvents, WithTitle
{
    protected $periodeRequest;
    protected $startDate;
    protected $endDate;
    protected $periodeDates;
    protected $data;
    protected $summary;

    public function __construct($periodeRequest = [0, -1, -2, -3, -4], $startDate = null, $endDate = null)
    {
        $this->periodeRequest = $periodeRequest;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        
        // Hitung tanggal untuk setiap periode
        $this->periodeDates = [];
        foreach ($this->periodeRequest as $weekOffset) {
            $start = Carbon::now()->addWeeks($weekOffset)->startOfWeek();
            $end = Carbon::now()->addWeeks($weekOffset)->endOfWeek();
            $labelBase = $weekOffset == 0 ? 'MINGGU INI' : abs($weekOffset) . ' MINGGU SEBELUMNYA';
            $this->periodeDates[] = [
                'offset' => $weekOffset,
                'start' => $start,
                'end' => $end,
                'label' => $labelBase . "\n(" . $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y') . ')',
                'labelShort' => $weekOffset == 0 ? 'Minggu Ini' : abs($weekOffset) . ' Minggu Sebelumnya'
            ];
        }
        
        // Generate data
        $this->generateData();
    }

    protected function generateData()
    {
        $penjahits = Penjahit::orderBy('nama_penjahit')->get();
        $result = [];
        
        $mingguIniStart = Carbon::now()->startOfWeek();
        $mingguIniEnd = Carbon::now()->endOfWeek();
        
        $totalDikerjakan = 0;
        $totalLebihDeadline = 0;
        $totalMasihDeadline = 0;
        $totalPengirimanMingguIni = 0;
        $totalRataRata = 0;
        $totalPeriode = [];
        $totalKenaikanRata2 = 0;
        $totalTertinggi = 0;
        $totalKenaikanTertinggi = 0;
        
        foreach ($this->periodeDates as $periode) {
            $totalPeriode['periode_' . $periode['offset']] = 0;
        }
        
        foreach ($penjahits as $index => $penjahit) {
            if (empty($penjahit->nama_penjahit)) {
                continue;
            }
            
            $spks = SpkCmt::where('id_penjahit', $penjahit->id_penjahit)
                ->with(['spkCuttingDistribusi', 'spkJasa.spkCuttingDistribusi'])
                ->get();
            
            $jmlTotalDikerjakan = 0;
            $lebihDariDeadline = 0;
            $masihDalamDeadline = 0;
            
            foreach ($spks as $spk) {
                $latestPengiriman = Pengiriman::where('id_spk', $spk->id_spk)
                    ->latest('tanggal_pengiriman')
                    ->first();
                
                $sisaBarang = 0;
                if ($latestPengiriman) {
                    $sisaBarang = $latestPengiriman->sisa_barang ?? 0;
                } else {
                    if ($spk->source_type === 'cutting' && $spk->spkCuttingDistribusi) {
                        $sisaBarang = $spk->spkCuttingDistribusi->jumlah_produk ?? 0;
                    } elseif ($spk->source_type === 'jasa' && $spk->spkJasa && $spk->spkJasa->spkCuttingDistribusi) {
                        $sisaBarang = $spk->spkJasa->spkCuttingDistribusi->jumlah_produk ?? 0;
                    }
                }
                
                if ($sisaBarang > 0) {
                    $jmlTotalDikerjakan += $sisaBarang;
                    
                    if ($spk->deadline) {
                        $deadline = Carbon::parse($spk->deadline);
                        if ($deadline->isPast()) {
                            $lebihDariDeadline += $sisaBarang;
                        } else {
                            $masihDalamDeadline += $sisaBarang;
                        }
                    } else {
                        $masihDalamDeadline += $sisaBarang;
                    }
                }
            }
            
            $pengirimanMingguIni = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
                ->whereBetween('pengiriman.tanggal_pengiriman', [$mingguIniStart, $mingguIniEnd])
                ->sum('pengiriman.total_barang_dikirim');
            
            $pengirimanPeriode = [];
            foreach ($this->periodeDates as $periode) {
                $pengiriman = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                    ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
                    ->whereBetween('pengiriman.tanggal_pengiriman', [$periode['start'], $periode['end']])
                    ->sum('pengiriman.total_barang_dikirim');
                
                $pengirimanPeriode['periode_' . $periode['offset']] = $pengiriman;
                $totalPeriode['periode_' . $periode['offset']] += $pengiriman;
            }
            
            $semuaPengiriman = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
                ->select('pengiriman.tanggal_pengiriman', 'pengiriman.total_barang_dikirim')
                ->get();
            
            $pengirimanPerMinggu = $semuaPengiriman->groupBy(function ($item) {
                return Carbon::parse($item->tanggal_pengiriman)->year . '-W' . 
                       str_pad(Carbon::parse($item->tanggal_pengiriman)->week, 2, '0', STR_PAD_LEFT);
            });
            
            $totalPerMinggu = [];
            foreach ($pengirimanPerMinggu as $minggu => $items) {
                $totalPerMinggu[] = $items->sum('total_barang_dikirim');
            }
            
            $rataRataPengirimanMingguan = count($totalPerMinggu) > 0 
                ? array_sum($totalPerMinggu) / count($totalPerMinggu) 
                : 0;
            
            $periodeUntukRataRata = array_filter($this->periodeRequest, function($offset) {
                return $offset < 0;
            });
            
            $rataRata2MingguTerakhir = 0;
            if (count($periodeUntukRataRata) >= 2) {
                $periodeUntukRataRata = array_slice(array_reverse($periodeUntukRataRata), 0, 2);
                $totalRataRata = 0;
                foreach ($periodeUntukRataRata as $offset) {
                    $key = 'periode_' . $offset;
                    $totalRataRata += $pengirimanPeriode[$key] ?? 0;
                }
                $rataRata2MingguTerakhir = $totalRataRata / count($periodeUntukRataRata);
            } elseif (count($periodeUntukRataRata) == 1) {
                $offset = reset($periodeUntukRataRata);
                $key = 'periode_' . $offset;
                $rataRata2MingguTerakhir = $pengirimanPeriode[$key] ?? 0;
            }
            $kenaikanPenurunanDariRata2 = $pengirimanMingguIni - $rataRata2MingguTerakhir;
            
            $pengirimanTertinggi = count($totalPerMinggu) > 0 ? max($totalPerMinggu) : 0;
            $kenaikanPenurunanDariTertinggi = $pengirimanMingguIni - $pengirimanTertinggi;
            
            $resultItem = [
                count($result) + 1,  // A: NO
                $penjahit->nama_penjahit,  // B: NAMA CMT
                (int)$lebihDariDeadline,  // C: LEBIH DARI DEADLINE (sub-header dari "JML TOTAL YANG MASIH DIKERJAKAN")
                (int)$masihDalamDeadline,  // D: MASIH DALAM DEADLINE (sub-header dari "JML TOTAL YANG MASIH DIKERJAKAN")
                (int)$pengirimanMingguIni,  // E: PENGIRIMAN MINGGU INI
                round($rataRataPengirimanMingguan, 0),  // F: RATA-RATA PENGIRIMAN MINGGUAN
            ];
            
            foreach ($this->periodeDates as $periode) {
                $key = 'periode_' . $periode['offset'];
                $resultItem[] = (int)($pengirimanPeriode[$key] ?? 0);
            }
            
            $resultItem[] = round($kenaikanPenurunanDariRata2, 0);
            $resultItem[] = (int)$pengirimanTertinggi;
            $resultItem[] = round($kenaikanPenurunanDariTertinggi, 0);
            
            $result[] = $resultItem;
            
            $totalDikerjakan += $jmlTotalDikerjakan;
            $totalLebihDeadline += $lebihDariDeadline;
            $totalMasihDeadline += $masihDalamDeadline;
            $totalPengirimanMingguIni += $pengirimanMingguIni;
            $totalRataRata += $rataRataPengirimanMingguan;
            $totalKenaikanRata2 += $kenaikanPenurunanDariRata2;
            $totalTertinggi += $pengirimanTertinggi;
            $totalKenaikanTertinggi += $kenaikanPenurunanDariTertinggi;
        }
        
        $this->data = $result;
        $this->summary = [
            'total_dikerjakan' => (int)$totalDikerjakan,
            'total_lebih_deadline' => (int)$totalLebihDeadline,
            'total_masih_deadline' => (int)$totalMasihDeadline,
            'total_pengiriman_minggu_ini' => (int)$totalPengirimanMingguIni,
            'total_rata_rata' => count($result) > 0 ? round($totalRataRata / count($result), 0) : 0,
            'total_periode' => $totalPeriode,
            'total_kenaikan_rata2' => round($totalKenaikanRata2, 0),
            'total_tertinggi' => (int)$totalTertinggi,
            'total_kenaikan_tertinggi' => round($totalKenaikanTertinggi, 0),
        ];
    }

    public function array(): array
    {
        // Return minimal data untuk membuat sheet, akan di-override di AfterSheet
        // Buat 1 dummy row dengan jumlah kolom yang sesuai
        $totalCols = 6 + count($this->periodeDates) + 3; // Fixed cols (A-F) + periode + last cols
        $dummyRow = array_fill(0, $totalCols, '');
        return [$dummyRow];
    }

    public function title(): string
    {
        return 'Data Dikerjakan & Pengiriman';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Get column letters (sesuai dengan struktur data baru: NO, NAMA CMT, LEBIH DARI DEADLINE, MASIH DALAM DEADLINE, PENGIRIMAN MINGGU INI, RATA-RATA)
                $cols = ['A', 'B', 'C', 'D', 'E', 'F'];
                $periodeCols = [];
                $colIndex = 'G'; // Periode dimulai dari G (setelah F untuk RATA-RATA)
                foreach ($this->periodeDates as $periode) {
                    $periodeCols[] = $colIndex++;
                }
                $lastCols = [$colIndex++, $colIndex++, $colIndex];
                $allCols = array_merge($cols, $periodeCols, $lastCols);
                $lastCol = end($allCols);
                
                // Title
                $sheet->setCellValue('A1', 'DATA YANG DIKERJAKAN DAN PENGIRIMAN CMT');
                $sheet->mergeCells('A1:' . $lastCol . '1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(25);
                
                // Header Row 1 (Grouped headers)
                $row2 = 2;
                
                // Set headers secara manual untuk menghindari index error
                $sheet->setCellValue('A' . $row2, 'NO');
                $sheet->setCellValue('B' . $row2, 'NAMA CMT');
                $sheet->setCellValue('C' . $row2, 'JML TOTAL YANG MASIH DIKERJAKAN /PCS');
                $sheet->mergeCells('C' . $row2 . ':D' . $row2);
                $sheet->setCellValue('E' . $row2, 'PENGIRIMAN MINGGU INI /PCS');
                $sheet->setCellValue('F' . $row2, 'RATA-RATA PENGIRIMAN MINGGUAN');
                
                // Add periode headers
                if (count($periodeCols) > 0) {
                    $sheet->setCellValue($periodeCols[0] . $row2, 'PENGIRIMAN PERIODE');
                    if (count($periodeCols) > 1) {
                        $sheet->mergeCells($periodeCols[0] . $row2 . ':' . end($periodeCols) . $row2);
                    }
                }
                
                // Add last headers
                $sheet->setCellValue($lastCols[0] . $row2, 'JML PCS KENAIKAN / PENURUNAN DARI RATA2 MINGGUAN');
                $sheet->setCellValue($lastCols[1] . $row2, 'PENGIRIMAN TERTINGGI PERIODE INI');
                $sheet->setCellValue($lastCols[2] . $row2, 'JML PCS KENAIKAN / PENURUNAN DARI KIRIM TERTINGGI');
                
                // Header Row 2 (Sub-headers)
                $row3 = 3;
                $sheet->setCellValue('C' . $row3, 'LEBIH DARI DEADLINE');
                $sheet->setCellValue('D' . $row3, 'MASIH DALAM DEADLINE');
                
                // Add periode sub-headers
                foreach ($periodeCols as $idx => $col) {
                    $periode = $this->periodeDates[$idx];
                    $sheet->setCellValue($col . $row3, $periode['label']);
                }
                
                // Add last headers (merged from row 2)
                foreach ($lastCols as $col) {
                    // Already set in row 2
                }
                
                // Merge cells for row 2 headers that span row 3
                $sheet->mergeCells('A' . $row2 . ':A' . $row3); // NO
                $sheet->mergeCells('B' . $row2 . ':B' . $row3); // NAMA CMT
                $sheet->mergeCells('E' . $row2 . ':E' . $row3); // PENGIRIMAN MINGGU INI
                $sheet->mergeCells('F' . $row2 . ':F' . $row3); // RATA-RATA
                foreach ($lastCols as $col) {
                    $sheet->mergeCells($col . $row2 . ':' . $col . $row3);
                }
                
                // Style header rows
                $headerRange = 'A' . $row2 . ':' . $lastCol . $row3;
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                
                // Header background colors
                $sheet->getStyle('A' . $row2 . ':A' . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ADDCEF'); // Light blue
                $sheet->getStyle('B' . $row2 . ':B' . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ADDCEF');
                $sheet->getStyle('C' . $row2 . ':D' . $row2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ADDCEF');
                $sheet->getStyle('C' . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FF6B6B'); // Red
                $sheet->getStyle('D' . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ADDCEF');
                $sheet->getStyle('E' . $row2 . ':E' . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7E8C9'); // Light green for PENGIRIMAN MINGGU INI
                $sheet->getStyle('F' . $row2 . ':F' . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9DCB4'); // Light yellow for RATA-RATA
                foreach ($periodeCols as $col) {
                    $sheet->getStyle($col . $row2 . ':' . $col . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ADDCEF');
                }
                $sheet->getStyle($lastCols[0] . $row2 . ':' . $lastCols[0] . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ADDCEF');
                $sheet->getStyle($lastCols[1] . $row2 . ':' . $lastCols[1] . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ADDCEF');
                $sheet->getStyle($lastCols[2] . $row2 . ':' . $lastCols[2] . $row3)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ADDCEF');
                
                // Data rows
                $startRow = 4;
                foreach ($this->data as $rowIdx => $rowData) {
                    $currentRow = $startRow + $rowIdx;
                    $colIdx = 0;
                    foreach ($rowData as $value) {
                        if (isset($allCols[$colIdx])) {
                            $col = $allCols[$colIdx];
                            $sheet->setCellValue($col . $currentRow, $value);
                            $colIdx++;
                        } else {
                            break; // Stop jika kolom sudah habis
                        }
                    }
                    
                    // Conditional formatting for data
                    $sheet->getStyle('C' . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFE5E5'); // Red background if > 0
                    $sheet->getStyle('D' . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F9FF'); // Light blue
                    $sheet->getStyle('F' . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCF4E3'); // Light green
                    $sheet->getStyle('G' . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDF2E2'); // Light yellow
                    
                    // Kenaikan/Penurunan colors
                    $kenaikanCol = $lastCols[0];
                    $kenaikanValue = $rowData[count($rowData) - 3];
                    if ($kenaikanValue > 0) {
                        $sheet->getStyle($kenaikanCol . $currentRow)->getFont()->getColor()->setRGB('198754'); // Green
                    } elseif ($kenaikanValue < 0) {
                        $sheet->getStyle($kenaikanCol . $currentRow)->getFont()->getColor()->setRGB('DC3545'); // Red
                    }
                    
                    $kenaikanTertinggiCol = $lastCols[2];
                    $kenaikanTertinggiValue = $rowData[count($rowData) - 1];
                    if ($kenaikanTertinggiValue > 0) {
                        $sheet->getStyle($kenaikanTertinggiCol . $currentRow)->getFont()->getColor()->setRGB('198754');
                    } elseif ($kenaikanTertinggiValue < 0) {
                        $sheet->getStyle($kenaikanTertinggiCol . $currentRow)->getFont()->getColor()->setRGB('DC3545');
                    }
                }
                
                // Style data rows
                if (count($this->data) > 0) {
                    $dataRange = 'A' . $startRow . ':' . $lastCol . ($startRow + count($this->data) - 1);
                    $sheet->getStyle($dataRange)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }
                
                // Summary row
                $summaryRow = count($this->data) > 0 ? $startRow + count($this->data) : $startRow;
                $sheet->setCellValue('A' . $summaryRow, 'TOTAL');
                $sheet->mergeCells('A' . $summaryRow . ':B' . $summaryRow);
                $sheet->setCellValue('C' . $summaryRow, $this->summary['total_dikerjakan']);
                $sheet->setCellValue('D' . $summaryRow, $this->summary['total_lebih_deadline']);
                $sheet->setCellValue('E' . $summaryRow, $this->summary['total_masih_deadline']);
                $sheet->setCellValue('F' . $summaryRow, $this->summary['total_pengiriman_minggu_ini']);
                $sheet->setCellValue('G' . $summaryRow, $this->summary['total_rata_rata']);
                
                $colIdx = 0;
                foreach ($periodeCols as $idx => $col) {
                    $key = 'periode_' . $this->periodeDates[$idx]['offset'];
                    $sheet->setCellValue($col . $summaryRow, $this->summary['total_periode'][$key] ?? 0);
                }
                
                $sheet->setCellValue($lastCols[0] . $summaryRow, $this->summary['total_kenaikan_rata2']);
                $sheet->setCellValue($lastCols[1] . $summaryRow, $this->summary['total_tertinggi']);
                $sheet->setCellValue($lastCols[2] . $summaryRow, $this->summary['total_kenaikan_tertinggi']);
                
                // Style summary row
                $summaryRange = 'A' . $summaryRow . ':' . $lastCol . $summaryRow;
                $sheet->getStyle($summaryRange)->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFEF5']],
                ]);
                
                // Column widths
                $sheet->getColumnDimension('A')->setWidth(8);
                $sheet->getColumnDimension('B')->setWidth(30);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(18);
                $sheet->getColumnDimension('E')->setWidth(22);
                $sheet->getColumnDimension('F')->setWidth(25);
                foreach ($periodeCols as $col) {
                    $sheet->getColumnDimension($col)->setWidth(18);
                }
                foreach ($lastCols as $col) {
                    $sheet->getColumnDimension($col)->setWidth(25);
                }
                
                $sheet->getRowDimension($row2)->setRowHeight(20);
                $sheet->getRowDimension($row3)->setRowHeight(20);
            },
        ];
    }
}
