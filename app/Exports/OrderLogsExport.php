<?php

namespace App\Exports;

use App\Models\NoDataGineeLog;
use App\Models\OrderLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrderLogsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;
    protected $filters;

    public function __construct($startDate, $endDate, array $filters = [])
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->filters = $filters;
    }

    public function collection()
    {
        $mode = $this->normalizePackingLogMode($this->filters['mode'] ?? null);
        $status = $this->filters['status'] ?? null;
        $tracking = $this->filters['tracking_number'] ?? null;
        $performedBy = $this->filters['performed_by'] ?? null;
        $action = $this->filters['action'] ?? null;
        $orderLogActions = $this->getOrderLogActionsForMode($mode);

        if ($action && $orderLogActions === null) {
            $orderLogActions = [$action];
        }

        if ($orderLogActions === []) {
            $orderLogs = collect();
        } else {
            $orderLogs = OrderLog::with(['order' => function ($q) {
                $q->select('id', 'order_number', 'tracking_number', 'status', 'total_amount');
            }])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($status, function ($q) use ($status) {
                $q->whereHas('order', function ($sub) use ($status) {
                    $sub->whereRaw('LOWER(status) = ?', [strtolower($status)]);
                });
            })
            ->when($tracking, function ($q) use ($tracking) {
                $q->whereHas('order', function ($sub) use ($tracking) {
                    $sub->where('tracking_number', 'LIKE', "%{$tracking}%");
                });
            })
            ->when($performedBy, function ($q) use ($performedBy) {
                $q->where('performed_by', 'LIKE', "%{$performedBy}%");
            })
            ->when($orderLogActions !== null, function ($q) use ($orderLogActions) {
                $q->whereIn('action', $orderLogActions);
            })
            ->orderBy('created_at', 'desc')
            ->get();
        }

        if ($this->shouldIncludeNoDataGineeLogs($mode)) {
            $ndgLogs = NoDataGineeLog::with(['order' => function ($q) {
                $q->select('id', 'order_number', 'tracking_number', 'status', 'total_amount');
            }])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->when($status, function ($q) use ($status) {
                $q->whereHas('order', function ($sub) use ($status) {
                    $sub->whereRaw('LOWER(status) = ?', [strtolower($status)]);
                });
            })
            ->when($tracking, function ($q) use ($tracking) {
                $q->where('tracking_number', 'LIKE', "%{$tracking}%");
            })
            ->when($performedBy, function ($q) use ($performedBy) {
                $q->where('scanner_name', 'LIKE', "%{$performedBy}%");
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ndg) {
                return (object) [
                    'created_at' => $ndg->created_at,
                    'action' => 'scan_no_data_ginee',
                    'performed_by' => $ndg->scanner_name,
                    'notes' => $ndg->notes,
                    'order' => $ndg->order ? (object) [
                        'order_number' => $ndg->order->order_number,
                        'tracking_number' => $ndg->order->tracking_number,
                        'status' => $ndg->order->status,
                        'total_amount' => $ndg->order->total_amount,
                    ] : (object) [
                        'order_number' => null,
                        'tracking_number' => $ndg->tracking_number,
                        'status' => 'NO DATA GINEE',
                        'total_amount' => 0,
                    ],
                ];
            });
        } else {
            $ndgLogs = collect();
        }

        return $orderLogs->concat($ndgLogs)->sortByDesc('created_at')->values();
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
            $this->getModeLabel($log->action ?? null),
            $log->action ?? '-',
            $log->performed_by ?? '-',
            $log->notes ?? '-',
            number_format($log->order->total_amount ?? 0, 0, ',', '.'),
        ];
    }

    private function normalizePackingLogMode($mode): ?string
    {
        $normalized = strtolower(trim((string) $mode));

        if ($normalized === '') {
            return null;
        }

        $allowedModes = ['normal', 'random', 'belum-barcode', 'no-data-ginee'];

        return in_array($normalized, $allowedModes, true) ? $normalized : null;
    }

    private function getOrderLogActionsForMode(?string $mode): ?array
    {
        if ($mode === null) {
            return null;
        }

        if ($mode === 'normal') {
            return ['scan_validasi'];
        }

        if ($mode === 'random') {
            return ['scan_validasi_random'];
        }

        if ($mode === 'belum-barcode') {
            return ['scan_validasi_belum_barcode'];
        }

        if ($mode === 'no-data-ginee') {
            return [];
        }

        return null;
    }

    private function shouldIncludeNoDataGineeLogs(?string $mode): bool
    {
        return $mode === null || $mode === 'no-data-ginee';
    }

    private function getModeLabel(?string $action): string
    {
        if ($action === 'scan_validasi_random') {
            return 'Random';
        }

        if ($action === 'scan_validasi_belum_barcode') {
            return 'Belum Barcode';
        }

        if ($action === 'scan_no_data_ginee') {
            return 'No Data Ginee';
        }

        return 'Normal';
    }
}
