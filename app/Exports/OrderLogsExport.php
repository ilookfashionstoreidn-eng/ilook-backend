<?php

namespace App\Exports;

use App\Services\PackingLogReportService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrderLogsExport implements FromQuery, WithHeadings, WithMapping, WithCustomChunkSize
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $service = app(PackingLogReportService::class);

        return $service->buildExportQuery(
            $service->prepareFilters($this->filters)
        );
    }

    public function headings(): array
    {
        return [
            'Tanggal Log',
            'Nomor Order',
            'Tracking Number',
            'Status Order',
            'Mode',
            'Action',
            'User',
            'Catatan',
            'Total Item',
            'Total Amount',
        ];
    }

    public function map($row): array
    {
        return [
            $row->created_at,
            $row->order_number ?? '-',
            $row->tracking_number ?? '-',
            $row->order_status ?? '-',
            app(PackingLogReportService::class)->getModeLabel($row->action ?? null),
            $row->action ?? '-',
            $row->performed_by ?? '-',
            $row->notes ?? '-',
            (int) ($row->total_items ?? 0),
            number_format($row->total_amount ?? 0, 0, ',', '.'),
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
