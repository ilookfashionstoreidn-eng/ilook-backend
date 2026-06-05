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

class SpkCuttingExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $startDate;
    protected $endDate;
    protected $statusFilter;

    public function __construct($statusFilter = 'all', $startDate = null, $endDate = null)
    {
        $this->statusFilter = $statusFilter;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $query = SpkCutting::with([
            "produk:id,nama_produk",
            "productList",
            "bagian.bahan.bahan",
            "bagian.bahan.skus",
            "tukangPola:id,nama",
            "tukangCutting:id,nama_tukang_cutting",
            "hasilCutting:id,spk_cutting_id,total_produk",
        ]);

        if ($this->statusFilter && $this->statusFilter !== "all") {
            $query->where("status_cutting", $this->statusFilter);
        }

        if ($this->startDate) {
            $start = Carbon::parse($this->startDate)->startOfDay();
            $query->where("created_at", ">=", $start);
        }

        if ($this->endDate) {
            $end = Carbon::parse($this->endDate)->endOfDay();
            $query->where("created_at", "<=", $end);
        }

        $spks = $query->orderBy("tanggal_batas_kirim", "asc")->orderBy("created_at", "desc")->get();

        $exportData = collect();

        foreach ($spks as $spk) {
            $multiplierCutting = (float) ($spk->productList->estimasi_cutting ?? 60);
            $multiplierCombi = (float) ($spk->productList->estimasi_combi ?? 60);

            $colorMap = [];

            if ($spk->relationLoaded("bagian")) {
                foreach ($spk->bagian as $bag) {
                    $namaBagian = strtolower(trim($bag->nama_bagian ?? ""));
                    if (strpos($namaBagian, "aksesor") !== false || strpos($namaBagian, "accessor") !== false) {
                        continue;
                    }

                    $isCombi = strpos($namaBagian, "combi") !== false || strpos($namaBagian, "kombinasi") !== false;
                    $multiplier = $isCombi ? $multiplierCombi : $multiplierCutting;

                    if ($bag->relationLoaded("bahan")) {
                        foreach ($bag->bahan as $bah) {
                            if ($bah->sumber_komponen === "bahan" && $bah->warna) {
                                $trimmedColor = trim($bah->warna);
                                if ($trimmedColor && $trimmedColor !== "-") {
                                    $totalRollQty = (float) ($bah->qty ?? 0);

                                    if ($bah->relationLoaded("skus") && $bah->skus->count() > 0) {
                                        $sizesInBahan = $bah->skus->map(function ($s) {
                                            return $s->product_size ?? $s->ukuran ?? "-";
                                        })->unique()->values();

                                        $rollQtyPerSize = $sizesInBahan->count() > 0 ? $totalRollQty / $sizesInBahan->count() : $totalRollQty;

                                        foreach ($sizesInBahan as $sizeLabel) {
                                            $key = $trimmedColor . "___" . $sizeLabel;
                                            if (!isset($colorMap[$key])) {
                                                $colorMap[$key] = ["qty" => 0, "estimasi" => 0, "warna" => $trimmedColor, "size" => $sizeLabel, "materials" => []];
                                            }
                                            $colorMap[$key]["qty"] += $rollQtyPerSize;
                                            $colorMap[$key]["estimasi"] += $rollQtyPerSize * $multiplier;
                                            $colorMap[$key]["materials"][] = [
                                                "kind" => $isCombi ? "kombinasi" : "utama",
                                                "colour" => $trimmedColor,
                                                "material" => $bah->bahan->nama_bahan ?? null,
                                                "material_group" => $bah->bahan->group_bahan ?? null
                                            ];
                                        }
                                    } else {
                                        $sizeLabel = $spk->sku->product_size ?? $spk->sku->ukuran ?? $spk->productList->product_size ?? $spk->produk->product_size ?? $spk->ukuran ?? "-";
                                        $key = $trimmedColor . "___" . $sizeLabel;
                                        if (!isset($colorMap[$key])) {
                                            $colorMap[$key] = ["qty" => 0, "estimasi" => 0, "warna" => $trimmedColor, "size" => $sizeLabel, "materials" => []];
                                        }
                                        $colorMap[$key]["qty"] += $totalRollQty;
                                        $colorMap[$key]["estimasi"] += $totalRollQty * $multiplier;
                                        $colorMap[$key]["materials"][] = [
                                            "kind" => $isCombi ? "kombinasi" : "utama",
                                            "colour" => $trimmedColor,
                                            "material" => $bah->bahan->nama_bahan ?? null,
                                            "material_group" => $bah->bahan->group_bahan ?? null
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $sizeGroups = [];
            $sizeKeys = [];
            foreach ($colorMap as $item) {
                $sizeLabel = $item["size"];
                if (!isset($sizeGroups[$sizeLabel])) {
                    $sizeGroups[$sizeLabel] = [];
                    $sizeKeys[] = $sizeLabel;
                }
                $sizeGroups[$sizeLabel][] = $item;
            }

            if (empty($sizeGroups)) {
                $hasilCuttingPcs = 0;
                if ($spk->relationLoaded("hasilCutting")) {
                    foreach ($spk->hasilCutting as $hasil) {
                        $hasilCuttingPcs += (int) ($hasil->total_produk ?? 0);
                    }
                }

                $totalQty = 0;
                if ($spk->relationLoaded("bagian")) {
                    foreach ($spk->bagian as $bagian) {
                        foreach ($bagian->bahan as $bahan) {
                            $totalQty += (float) ($bahan->qty ?? 0);
                        }
                    }
                }

                $qty_order = (int) ($spk->jumlah_asumsi_produk ?? 0);

                $exportData->push([
                    "spk" => $spk,
                    "suffix" => "",
                    "suffix_id" => $spk->id_spk_cutting ?? "-",
                    "sizeLabel" => $spk->productList->product_size ?? "-",
                    "qty_order" => $qty_order,
                    "qty_kirim" => $hasilCuttingPcs,
                    "est_rol"   => $totalQty,
                    "estimasi_qty" => $qty_order,
                    "colors" => $spk->productList->product_colour ?? "-",
                    "materials_json" => $spk->productList->materials ?? "-",
                ]);
            } else {
                foreach ($sizeKeys as $sizeIdx => $sizeKey) {
                    $groupItems = $sizeGroups[$sizeKey];

                    $suffix = count($sizeKeys) > 1 ? "-" . chr(65 + $sizeIdx) : "";
                    $mergedId = ($spk->id_spk_cutting ?? "") . $suffix;

                    $groupTotalQty = array_sum(array_column($groupItems, "qty"));
                    $groupTotalEstimasi = array_sum(array_column($groupItems, "estimasi"));

                    $colors = array_unique(array_column($groupItems, "warna"));
                    $colorStr = count($colors) > 0 ? implode(", ", $colors) : "-";

                    $allMaterials = [];
                    foreach ($groupItems as $gi) {
                        foreach ($gi["materials"] as $m) {
                            $allMaterials[] = $m;
                        }
                    }
                    $materialsJson = json_encode($allMaterials);

                    $exportData->push([
                        "spk" => $spk,
                        "suffix" => $suffix,
                        "suffix_id" => $mergedId,
                        "sizeLabel" => $sizeKey,
                        "qty_order" => round($groupTotalEstimasi),
                        "qty_kirim" => null,
                        "est_rol"   => $groupTotalQty,
                        "estimasi_qty" => round($groupTotalEstimasi),
                        "colors" => $colorStr,
                        "materials_json" => $materialsJson,
                    ]);
                }
            }
        }

        return $exportData;
    }

    public function headings(): array
    {
        return [
            "no_spk",
            "tukang_potong",
            "no_seri",
            "tgl_spk",
            "tgl_ambil",
            "tgl_deadline",
            "pos",
            "pic",
            "product_group",
            "product_size",
            "product_source",
            "product_colour",
            "product_colour",
            "product",
            "qty_order",
            "qty_kirim",
            "qty_claim",
            "qty_sisa",
            "spk_status",
            "est_rol",
            "estimasi_cutting",
            "estimasi_qty",
            "product_material_group_1",
            "price_cutting",
            "price_cmt",
        ];
    }

    public function map($row): array
    {
        $spk = $row["spk"];

        $qty_kirim = $row["qty_kirim"];
        $qty_order = $row["qty_order"];
        $qty_sisa = $qty_kirim === null ? $qty_order : $qty_order - $qty_kirim;

        // "id internalnya saja dengan suffix -"
        $no_spk = $spk->id . ($row["suffix"] ?? "");

        $nama_tukang = strtoupper(trim($spk->tukangCutting->nama_tukang_cutting ?? ""));
        $inisial = "XX";
        if ($nama_tukang) {
            if (strpos($nama_tukang, "ERIK") !== false || strpos($nama_tukang, "ERIC") !== false) {
                $inisial = "EK";
            } else {
                $inisial = substr($nama_tukang, 0, 2);
            }
        }
        
        $no_seri = $inisial . "-" . $no_spk;

        return [
            $no_spk, // no_spk
            $spk->tukangCutting->nama_tukang_cutting ?? "-", // tukang_potong
            $no_seri, // no_seri
            $spk->created_at ? Carbon::parse($spk->created_at)->format("d/m/Y") : "-", // tgl_spk
            "-", // tgl_ambil
            $spk->tanggal_batas_kirim ? Carbon::parse($spk->tanggal_batas_kirim)->format("d/m/Y") : "-", // tgl_deadline
            "Cutting", // pos
            $spk->pic ?? "-", // pic
            $spk->productList->product_group ?? "-", // product_group
            $row["sizeLabel"], // product_size
            $spk->productList->product_source ?? "-", // product_source
            $spk->productList->product_colour ?? "-", // product_colour 1
            $spk->productList->product_colour ?? "-", // product_colour 2
            $spk->productList->product ?? $spk->produk->nama_produk ?? "-", // product
            $qty_order, // qty_order
            $qty_kirim, // qty_kirim
            null, // qty_claim
            $qty_sisa, // qty_sisa
            $spk->status_cutting ?? "-", // spk_status
            $row["est_rol"], // est_rol
            $spk->productList->estimasi_cutting ?? "-", // estimasi_cutting
            $row["estimasi_qty"], // estimasi_qty
            $row["materials_json"], // product_material_group_1
            $spk->productList->price_cutting ?? $spk->harga_per_pcs ?? 0, // price_cutting
            $spk->productList->price_cmt ?? 0, // price_cmt
        ];
    }

    public function columnWidths(): array
    {
        return [
            "A" => 15,
            "B" => 20,
            "C" => 15,
            "D" => 15,
            "E" => 15,
            "F" => 15,
            "G" => 15,
            "H" => 15,
            "I" => 20,
            "J" => 12,
            "K" => 20,
            "L" => 15,
            "M" => 15,
            "N" => 30,
            "O" => 12,
            "P" => 12,
            "Q" => 12,
            "R" => 12,
            "S" => 15,
            "T" => 12,
            "U" => 15,
            "V" => 15,
            "W" => 50,
            "X" => 15,
            "Y" => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle("A1:Y1")->applyFromArray([
            "font" => [
                "bold" => true,
                "color" => ["rgb" => "FFFFFF"],
                "size" => 12,
            ],
            "fill" => [
                "fillType" => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                "startColor" => ["rgb" => "0487D8"],
            ],
            "alignment" => [
                "horizontal" => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                "vertical" => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            "borders" => [
                "allBorders" => [
                    "borderStyle" => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    "color" => ["rgb" => "000000"],
                ],
            ],
        ]);
        
        return [];
    }
}
