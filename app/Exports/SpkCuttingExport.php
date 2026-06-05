<?php



namespace App\Exports;



use App\Models\SpkCutting;

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



class SpkCuttingExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths

{

    protected $statusFilter;

    protected $startDate;

    protected $endDate;



    public function __construct($statusFilter = 'all', $startDate = null, $endDate = null)

    {

        $this->statusFilter = $statusFilter;

        $this->startDate = $startDate;

        $this->endDate = $endDate;
    }



    public function collection()

    {

        $query = SpkCutting::with([

            'produk:id,nama_produk',

            'bagian.bahan', // untuk hitung totall

            'tukangPola:id,nama', // untuk tukang pola

            'hasilCutting:id,spk_cutting_id,total_produk', // untuk hasil cutting pcs

        ]);



        // Filter berdasarkan status jika ada

        if ($this->statusFilter && $this->statusFilter !== 'all') {

            $query->where('status_cutting', $this->statusFilter);
        }



        // Filter berdasarkan tanggal data ditambahkan (created_at)

        if ($this->startDate) {

            $start = Carbon::parse($this->startDate)->startOfDay();

            $query->where('created_at', '>=', $start);
        }

        if ($this->endDate) {

            $end = Carbon::parse($this->endDate)->endOfDay();

            $query->where('created_at', '<=', $end);
        }



        // Urutkan berdasarkan deadline terdekat (tanggal_batas_kirim ASC)

        return $query->orderBy('tanggal_batas_kirim', 'asc')

            ->orderBy('created_at', 'desc')

            ->get();
    }



    public function headings(): array

    {

        return [

            'No',

            'Tgl SPK Cutting',

            'Tukang Pola',

            'Nomor Seri',

            'Nama Produk',

            'Total Roll',

            'Asumsi',

            'Jenis SPK',

            'Deadline',

            'Hasil Cutting Pcs',

            'Status',

            'Keterangan',

        ];
    }



    public function map($spk): array

    {

        // Hitung total (jumlah qty semua bahan di semua bagian)

        $totalQty = 0;

        if ($spk->relationLoaded('bagian')) {

            foreach ($spk->bagian as $bagian) {

                foreach ($bagian->bahan as $bahan) {

                    $totalQty += (float) ($bahan->qty ?? 0);
                }
            }
        }



        // Hitung sisa waktu (boleh minus jika sudah lewat)

        $sisaWaktuText = 'Belum ada deadline';

        if ($spk->tanggal_batas_kirim) {

            $deadline = Carbon::parse($spk->tanggal_batas_kirim)->startOfDay();

            $today = Carbon::now()->startOfDay();

            $diff = $today->diffInDays($deadline, false); // bisa negatif

            $sisaWaktuText = $diff . ' hari';
        }



        // Hitung hasil cutting pcs (total_produk dari hasil_cutting)

        $hasilCuttingPcs = 0;

        if ($spk->relationLoaded('hasilCutting')) {

            foreach ($spk->hasilCutting as $hasil) {

                $hasilCuttingPcs += (int) ($hasil->total_produk ?? 0);
            }
        }

        return [

            '', // No akan diisi otomatis di Excel

            $spk->created_at ? Carbon::parse($spk->created_at)->format('d/m/Y') : '-', // Tgl SPK Cutting

            $spk->tukangPola->nama ?? '-', // Tukang Pola

            $spk->id_spk_cutting ?? '-', // Nomor Seri

            $spk->produk->nama_produk ?? '-', // Nama Produk

            $totalQty, // Total Roll

            $spk->jumlah_asumsi_produk ?? '-', // Asumsi

            $spk->jenis_spk ?? '-', // Jenis SPK

            $spk->tanggal_batas_kirim ? Carbon::parse($spk->tanggal_batas_kirim)->format('d/m/Y') : '-', // Deadline

            $hasilCuttingPcs, // Hasil Cutting Pcs

            $spk->status_cutting ?? '-', // Status

            $spk->keterangan ?? '-', // Keterangan

        ];
    }



    public function columnWidths(): array

    {

        return [

            'A' => 8,   // No

            'B' => 15,  // Tgl SPK Cutting

            'C' => 20,  // Tukang Pola

            'D' => 18,  // Nomor Seri

            'E' => 30,  // Nama Produk

            'F' => 12,  // Total Roll

            'G' => 12,  // Asumsi

            'H' => 15,  // Jenis SPK

            'I' => 15,  // Deadline

            'J' => 18,  // Hasil Cutting Pcs

            'K' => 15,  // Status

            'L' => 40,  // Keterangan

        ];
    }



    public function styles(Worksheet $sheet)

    {

        // Style untuk header

        $sheet->getStyle('A1:L1')->applyFromArray([

            'font' => [

                'bold' => true,

                'color' => ['rgb' => 'FFFFFF'],

                'size' => 12,

            ],

            'fill' => [

                'fillType' => Fill::FILL_SOLID,

                'startColor' => ['rgb' => '0487D8'],

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



        // Auto number untuk kolom A

        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {

            $sheet->setCellValue('A' . $row, $row - 1);
        }



        // Style untuk data rows

        $sheet->getStyle('A2:L' . $highestRow)->applyFromArray([

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



        // Center / right alignment untuk kolom tertentu

        $sheet->getStyle('A:A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No

        $sheet->getStyle('B:B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Tgl SPK Cutting

        $sheet->getStyle('C:C')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // Tukang Pola

        $sheet->getStyle('D:D')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Nomor Seri

        $sheet->getStyle('E:E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // Nama Produk

        $sheet->getStyle('F:F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Total Roll

        $sheet->getStyle('G:G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Asumsi

        $sheet->getStyle('H:H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Jenis SPK

        $sheet->getStyle('I:I')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Deadline

        $sheet->getStyle('J:J')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Hasil Cutting Pcs

        $sheet->getStyle('K:K')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Status



        // Wrap text untuk kolom keterangan

        $sheet->getStyle('L:L')->getAlignment()->setWrapText(true);



        // Set row height untuk header

        $sheet->getRowDimension(1)->setRowHeight(25);



        return $sheet;
    }
}
