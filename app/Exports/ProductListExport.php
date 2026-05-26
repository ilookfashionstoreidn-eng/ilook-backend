<?php

namespace App\Exports;

use App\Models\ProductList;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductListExport implements FromQuery, WithHeadings, WithMapping, WithCustomChunkSize, WithColumnWidths, WithEvents
{
    use Exportable;

    private const MATERIAL_EXPORT_LIMIT = 6;

    private array $columns;
    private array $filters;

    public function __construct(array $columns, array $filters = [])
    {
        $this->columns = self::sanitizeColumns($columns);
        $this->filters = $filters;
    }

    public static function availableColumns(): array
    {
        $columns = [
            'sku_name' => ['heading' => 'sku_name', 'source' => 'sku_name'],
            'product' => ['heading' => 'product', 'source' => 'product'],
            'product_group' => ['heading' => 'product_group', 'source' => 'product_group'],
            'product_size' => ['heading' => 'product_size', 'source' => 'product_size'],
            'product_source' => ['heading' => 'product_source', 'source' => 'product_source'],
            'product_colour' => ['heading' => 'product_colour', 'source' => 'product_colour'],
        ];

        for ($index = 1; $index <= self::MATERIAL_EXPORT_LIMIT; $index++) {
            $columns["product_material_{$index}"] = ['heading' => "product_material_{$index}", 'source' => 'materials'];
            $columns["product_colour_{$index}"] = ['heading' => "product_colour_{$index}", 'source' => 'materials'];
            $columns["product_material_group_{$index}"] = ['heading' => "product_material_group_{$index}", 'source' => 'materials'];
        }

        return array_merge($columns, [
            'estimasi_cutting' => ['heading' => 'estimasi_cutting', 'source' => 'estimasi_cutting'],
            'estimasi_combi' => ['heading' => 'estimasi_combi', 'source' => 'estimasi_combi'],
            'id_s' => ['heading' => 'id_s', 'source' => 'id_s'],
            'id_m' => ['heading' => 'id_m', 'source' => 'id_m'],
            'id_l' => ['heading' => 'id_l', 'source' => 'id_l'],
            'id_xl' => ['heading' => 'id_xl', 'source' => 'id_xl'],
            'pj_dress' => ['heading' => 'pj_dress', 'source' => 'pj_dress'],
            'pj_celana' => ['heading' => 'pj_celana', 'source' => 'pj_celana'],
            'pj_baju' => ['heading' => 'pj_baju', 'source' => 'pj_baju'],
            'notes_spk' => ['heading' => 'notes_spk', 'source' => 'notes_spk'],
            'price_cmt' => ['heading' => 'price_cmt', 'source' => 'price_cmt'],
            'price_cutting' => ['heading' => 'price_cutting', 'source' => 'price_cutting'],
            'material_count' => ['heading' => 'material_count', 'source' => 'material_count'],
            'id' => ['heading' => 'id', 'source' => 'id'],
            'created_at' => ['heading' => 'created_at', 'source' => 'created_at'],
            'updated_at' => ['heading' => 'updated_at', 'source' => 'updated_at'],
        ]);
    }

    public static function defaultColumns(): array
    {
        return [
            'sku_name',
            'product',
            'product_group',
            'product_size',
            'product_source',
            'product_colour',
            'product_material_1',
            'product_colour_1',
            'product_material_group_1',
            'product_material_2',
            'product_colour_2',
            'product_material_group_2',
            'estimasi_cutting',
            'estimasi_combi',
            'id_s',
            'id_m',
            'id_l',
            'id_xl',
            'pj_dress',
            'pj_celana',
            'pj_baju',
            'notes_spk',
            'price_cmt',
            'price_cutting',
        ];
    }

    public static function sanitizeColumns(array $columns): array
    {
        $available = array_keys(self::availableColumns());
        $columns = array_values(array_unique(array_filter($columns, function ($column) use ($available) {
            return is_string($column) && in_array($column, $available, true);
        })));

        return !empty($columns) ? $columns : self::defaultColumns();
    }

    public function query()
    {
        $query = ProductList::query()->select($this->selectColumns());

        $search = trim((string) ($this->filters['search'] ?? ''));
        $productGroup = trim((string) ($this->filters['product_group'] ?? ''));
        $productSource = trim((string) ($this->filters['product_source'] ?? ''));
        $sortBy = $this->filters['sortBy'] ?? 'id';
        $sortOrder = strtolower((string) ($this->filters['sortOrder'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'product', 'sku_name', 'product_group', 'product_source', 'created_at', 'updated_at'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        if ($search !== '') {
            $searchPrefix = $this->escapeLike($search) . '%';

            $query->where(function ($q) use ($search, $searchPrefix) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('product', 'like', $searchPrefix)
                    ->orWhere('sku_name', 'like', $searchPrefix)
                    ->orWhere('product_group', 'like', $searchPrefix)
                    ->orWhere('product_size', 'like', $searchPrefix)
                    ->orWhere('product_source', 'like', $searchPrefix)
                    ->orWhere('product_colour', 'like', $searchPrefix)
                    ->orWhere('id_s', 'like', $searchPrefix)
                    ->orWhere('id_m', 'like', $searchPrefix)
                    ->orWhere('id_l', 'like', $searchPrefix)
                    ->orWhere('id_xl', 'like', $searchPrefix);
            });
        }

        if ($productGroup !== '') {
            $query->where('product_group', $productGroup);
        }

        if ($productSource !== '') {
            $query->where('product_source', $productSource);
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($sortBy !== 'id') {
            $query->orderBy('id', $sortOrder);
        }

        return $query;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }
}
