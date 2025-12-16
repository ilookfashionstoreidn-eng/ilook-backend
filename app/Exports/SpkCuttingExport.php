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

            'bagian.bahan', // untuk hitung total

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

            'Nomor Seri',

            'Nama Produk',

            'Total',

            'Deadline',

            'Sisa Waktu (Hari)',

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



        return [

            '', // No akan diisi otomatis di Excel

            $spk->id_spk_cutting ?? '-',

            $spk->produk->nama_produk ?? '-',

            $totalQty,

            $spk->tanggal_batas_kirim ? Carbon::parse($spk->tanggal_batas_kirim)->format('d/m/Y') : '-',

            $sisaWaktuText,

            $spk->keterangan ?? '-',

        ];
    }



    public function columnWidths(): array

    {

        return [

            'A' => 8,   // No

            'B' => 18,  // Nomor Seri

            'C' => 30,  // Nama Produk

            'D' => 12,  // Total

            'E' => 15,  // Deadline

            'F' => 18,  // Sisa Waktu (Hari)

            'G' => 40,  // Keterangan

        ];
    }



    public function styles(Worksheet $sheet)

    {

        // Style untuk header

        $sheet->getStyle('A1:G1')->applyFromArray([

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

        $sheet->getStyle('A2:G' . $highestRow)->applyFromArray([

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

        $sheet->getStyle('A:A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('E:E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('F:F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('D:D')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);



        // Wrap text untuk kolom keterangan

        $sheet->getStyle('G:G')->getAlignment()->setWrapText(true);



        // Set row height untuk header

        $sheet->getRowDimension(1)->setRowHeight(25);



        return $sheet;
    }
}
