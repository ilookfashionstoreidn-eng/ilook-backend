<?php

namespace App\Http\Controllers;

use App\Exports\OrderReturnLogsExport;
use App\Models\Order;
use App\Models\OrderReturnLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class OrderReturnLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->prepareFilters($request);

        $query = $this->baseFilteredQuery($filters)
            ->with(['order.items'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $query->paginate($this->normalizePerPage($request->input('per_page', 25)));

        $paginator->getCollection()->transform(function (OrderReturnLog $returnLog) {
            return $this->formatReturnLog($returnLog);
        });

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function show(int $id)
    {
        $returnLog = OrderReturnLog::with(['order.items'])->findOrFail($id);

        return response()->json([
            'message' => 'Detail log return berhasil diambil',
            'data' => $this->formatReturnLog($returnLog),
        ]);
    }

    public function summary(Request $request)
    {
        $filters = $this->prepareFilters($request);
        $baseQuery = $this->baseFilteredQuery($filters);

        $totalReturn = (clone $baseQuery)->count();
        $totalItems = (clone $baseQuery)
            ->join('order', 'order.id', '=', 'order_return_logs.order_id')
            ->sum(DB::raw('COALESCE(`order`.`total_qty`, 0)'));

        $petugasSummary = (clone $baseQuery)
            ->select('performed_by', DB::raw('COUNT(*) as total_returns'))
            ->groupBy('performed_by')
            ->orderByDesc('total_returns')
            ->get()
            ->map(function ($row) {
                return [
                    'performed_by' => $row->performed_by,
                    'total_returns' => (int) $row->total_returns,
                    'total_returns_formatted' => $this->formatWholeNumber($row->total_returns),
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Summary return berhasil diambil',
            'data' => [[
                'total_return' => (int) $totalReturn,
                'total_return_formatted' => $this->formatWholeNumber($totalReturn),
                'total_items' => (int) $totalItems,
                'total_items_formatted' => $this->formatWholeNumber($totalItems),
            ]],
            'petugas_summary' => $petugasSummary,
            'filters' => [
                'start_date' => $filters['start_date']->toDateString(),
                'end_date' => $filters['end_date']->toDateString(),
                'tracking_number' => $filters['tracking_number'],
                'performed_by' => $filters['performed_by'],
            ],
        ]);
    }

    public function export(Request $request)
    {
        $fileName = 'return_logs_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new OrderReturnLogsExport(
                $request->only(['start_date', 'end_date', 'tracking_number', 'performed_by'])
            ),
            $fileName
        );
    }

    private function prepareFilters(Request $request): array
    {
        $startDate = $request->input('start_date') ?: now()->toDateString();
        $endDate = $request->input('end_date') ?: $startDate;

        return [
            'start_date' => Carbon::parse($startDate)->startOfDay(),
            'end_date' => Carbon::parse($endDate)->endOfDay(),
            'tracking_number' => trim((string) $request->input('tracking_number', '')),
            'performed_by' => trim((string) $request->input('performed_by', '')),
        ];
    }

    private function baseFilteredQuery(array $filters): Builder
    {
        return OrderReturnLog::query()
            ->whereBetween('order_return_logs.created_at', [$filters['start_date'], $filters['end_date']])
            ->when($filters['tracking_number'] !== '', function (Builder $query) use ($filters) {
                $query->where('order_return_logs.tracking_number', 'like', '%' . $filters['tracking_number'] . '%');
            })
            ->when($filters['performed_by'] !== '', function (Builder $query) use ($filters) {
                $query->where('order_return_logs.performed_by', 'like', '%' . $filters['performed_by'] . '%');
            });
    }

    private function normalizePerPage($perPage): int
    {
        $allowedValues = [25, 50, 100];
        $normalized = (int) $perPage;

        return in_array($normalized, $allowedValues, true) ? $normalized : 25;
    }

    private function formatReturnLog(OrderReturnLog $returnLog): array
    {
        $order = $returnLog->order;
        $items = $order ? $order->items : collect();
        $totalItems = $order ? (int) ($order->total_qty ?: $items->sum('quantity')) : 0;

        return [
            'id' => $returnLog->id,
            'tracking_number' => $returnLog->tracking_number,
            'performed_by' => $returnLog->performed_by,
            'notes' => $returnLog->notes,
            'created_at' => optional($returnLog->created_at)->toDateTimeString(),
            'total_items' => $totalItems,
            'total_items_formatted' => $this->formatWholeNumber($totalItems),
            'has_detail' => $items->isNotEmpty(),
            'order' => $order ? $this->formatOrder($order) : null,
        ];
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'total_qty' => (int) ($order->total_qty ?: $order->items->sum('quantity')),
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'price' => $item->price,
                    'image' => $item->image,
                ];
            })->values(),
        ];
    }

    private function formatWholeNumber($value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }
}
