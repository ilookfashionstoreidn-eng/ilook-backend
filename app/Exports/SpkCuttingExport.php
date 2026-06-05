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
            'productList', // Added product_lists
            'bagian.bahan', // untuk hitung total
            'tukangPola:id,nama', // untuk tukang pola
            'tukangCutting:id,nama_tukang_cutting', // untuk tukang potong
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
            'no_spk',
            'tukang_potong',
            'no_seri',
            'tgl_spk',
            'tgl_ambil',
            'tgl_deadline',
            'pos',
            'pic',
            'product_group',
            'product_size',
            'product_source',
            'product_colour',
            'product',
            'qty_order',
            'qty_kirim',
            'qty_claim',
            'qty_sisa',
            'spk_status',
            'est_rol',
            'estimasi_cutting',
            'estimasi_qty',
            'product_material_group_1',
            'price_cutting',
            'price_cmt',
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

        $qty_kirim = $hasilCuttingPcs;
        $qty_order = (int) ($spk->jumlah_asumsi_produk ?? 0);
        $qty_sisa = $qty_order - $qty_kirim;

        return [
            $spk->id ?? '-', // no_spk
            $spk->tukangCutting->nama_tukang_cutting ?? '-', // tukang_potong
            $spk->id_spk_cutting ?? '-', // no_seri
            $spk->created_at ? Carbon::parse($spk->created_at)->format('Y-m-d') : '-', // tgl_spk
            '-', // tgl_ambil
            $spk->tanggal_batas_kirim ? Carbon::parse($spk->tanggal_batas_kirim)->format('Y-m-d') : '-', // tgl_deadline
            'Cutting', // pos
            $spk->pic ?? '-', // pic
            $spk->productList->product_group ?? '-', // product_group
            $spk->productList->product_size ?? '-', // product_size
            $spk->productList->product_source ?? '-', // product_source
            $spk->productList->product_colour ?? '-', // product_colour
            $spk->productList->product ?? $spk->produk->nama_produk ?? '-', // product
            $qty_order, // qty_order
            $qty_kirim, // qty_kirim
            0, // qty_claim
            $qty_sisa, // qty_sisa
            $spk->status_cutting ?? '-', // spk_status
            $totalQty, // est_rol
            $spk->productList->estimasi_cutting ?? '-', // estimasi_cutting
            $qty_order, // estimasi_qty
            $spk->productList->materials ?? '-', // product_material_group_1
            $spk->productList->price_cutting ?? $spk->harga_per_pcs ?? 0, // price_cutting
            $spk->productList->price_cmt ?? 0, // price_cmt
        ];
    }



    public function columnWidths(): array
    {
        return [
            'A' => 10,  // no_spk
            'B' => 20,  // tukang_potong
            'C' => 18,  // no_seri
            'D' => 15,  // tgl_spk
            'E' => 15,  // tgl_ambil
            'F' => 15,  // tgl_deadline
            'G' => 12,  // pos
            'H' => 15,  // pic
            'I' => 15,  // product_group
            'J' => 12,  // product_size
            'K' => 15,  // product_source
            'L' => 15,  // product_colour
            'M' => 30,  // product
            'N' => 12,  // qty_order
            'O' => 12,  // qty_kirim
            'P' => 12,  // qty_claim
            'Q' => 12,  // qty_sisa
            'R' => 15,  // spk_status
            'S' => 12,  // est_rol
            'T' => 15,  // estimasi_cutting
            'U' => 15,  // estimasi_qty
            'V' => 25,  // product_material_group_1
            'W' => 15,  // price_cutting
            'X' => 15,  // price_cmt
        ];
    }



    public function styles(Worksheet $sheet)

    {

        // Style untuk header

        $sheet->getStyle('A1:X1')->applyFromArray([

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



        // Auto number untuk kolom A - dihapus karena no_spk dipakai

        // Style untuk data rows
        $highestRow = $sheet->getHighestRow();

        $sheet->getStyle('A2:X' . $highestRow)->applyFromArray([

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
        $sheet->getStyle('A:G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('N:Q')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('S:U')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('W:X')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('R:R')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);



        // Set row height untuk header

        $sheet->getRowDimension(1)->setRowHeight(25);



        return $sheet;
    }
}
