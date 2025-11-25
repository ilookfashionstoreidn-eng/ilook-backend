<?php

namespace App\Exports;

use App\Models\OrderLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrderLogsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;
    protected $action;

    public function __construct($startDate, $endDate, $action = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->action = $action;
    }

    public function collection()
    {
        $query = OrderLog::with(['order' => function ($q) {
            $q->select('id', 'order_number', 'tracking_number', 'status', 'total_amount');
        }])
        ->whereBetween('created_at', [$this->startDate, $this->endDate])
        ->orderBy('created_at', 'desc');

        if ($this->action) {
            $query->where('action', $this->action);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal Log',
            'Nomor Order',
            'Tracking Number',
            'Status Order',
            'Action',
            'User',
            'Catatan',
            'Total Amount',
        ];
    }

    public function map($log): array
    {
        return [
            $log->created_at->format('Y-m-d H:i:s'),
            $log->order->order_number ?? '-',
            $log->order->tracking_number ?? '-',
            $log->order->status ?? '-',
            $log->action ?? '-',
            $log->performed_by ?? '-',
            $log->notes ?? '-',
            number_format($log->order->total_amount ?? 0, 0, ',', '.'),
        ];
    }
}
