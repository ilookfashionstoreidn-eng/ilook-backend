<?php

namespace App\Exports;

use App\Models\OrderReturnLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrderReturnLogsExport implements FromQuery, WithHeadings, WithMapping, WithCustomChunkSize
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $this->prepareFilters($filters);
    }

    public function query()
    {
        return OrderReturnLog::query()
            ->with(['order.items'])
            ->whereBetween('order_return_logs.created_at', [
                $this->filters['start_date'],
                $this->filters['end_date'],
            ])
            ->when($this->filters['tracking_number'] !== '', function (Builder $query) {
                $query->where(
                    'order_return_logs.tracking_number',
                    'like',
                    '%' . $this->filters['tracking_number'] . '%'
                );
            })
            ->when($this->filters['performed_by'] !== '', function (Builder $query) {
                $query->where(
                    'order_return_logs.performed_by',
                    'like',
                    '%' . $this->filters['performed_by'] . '%'
                );
            })
            ->orderByDesc('order_return_logs.created_at')
            ->orderByDesc('order_return_logs.id');
    }

    public function headings(): array
    {
        return [
            'Tanggal Log',
            'Tracking Number',
            'Nomor Order',
            'Status Order',
            'Petugas',
            'Customer',
            'No HP',
            'Total Item',
            'SKU',
            'Catatan',
        ];
    }

    public function map($returnLog): array
    {
        $order = $returnLog->order;
        $items = $order ? $order->items : collect();
        $totalItems = $order ? (int) ($order->total_qty ?: $items->sum('quantity')) : 0;
        $skuList = $items
            ->map(function ($item) {
                return trim($item->sku . ' x' . (int) $item->quantity);
            })
            ->filter()
            ->implode(', ');

        return [
            optional($returnLog->created_at)->format('Y-m-d H:i:s'),
            $returnLog->tracking_number ?? '-',
            $order->order_number ?? '-',
            $order->status ?? '-',
            $returnLog->performed_by ?? '-',
            $order->customer_name ?? '-',
            $order->customer_phone ?? '-',
            $totalItems,
            $skuList ?: '-',
            $returnLog->notes ?? '-',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    private function prepareFilters(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->toDateString();
        $endDate = $filters['end_date'] ?? $startDate;

        return [
            'start_date' => Carbon::parse($startDate)->startOfDay(),
            'end_date' => Carbon::parse($endDate)->endOfDay(),
            'tracking_number' => trim((string) ($filters['tracking_number'] ?? '')),
            'performed_by' => trim((string) ($filters['performed_by'] ?? '')),
        ];
    }
}
