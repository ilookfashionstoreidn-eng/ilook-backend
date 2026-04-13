<?php

namespace App\Http\Controllers;

use App\Exports\OrderLogsExport;
use App\Jobs\GeneratePackingLogExport;
use App\Models\PackingLogExport;
use App\Models\GudangProdukActivityLog;
use App\Models\GudangProdukWorkspaceStockEntry;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderLog;
use App\Services\PackingLogReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\OrderItemSerial;
use App\Models\Sku;
use Illuminate\Support\Facades\Storage;



class OrderController extends Controller
{
    public function showByTracking($trackingNumber)
    {
        $order = $this->findOrderByTracking($trackingNumber, ['items']);

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if ($order->is_packed == '1'){
            return response()->json(['message' => 'Orderan sudah di packing'], 409);
        }

        return response()->json($order);
    }

    public function validateScan(Request $request, $trackingNumber)
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1|max:1000',
                'items.*.sku' => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1|max:10000',
                'items.*.serials' => 'required|array|min:1',
                'items.*.serials.*' => 'required|string|min:1|max:255',
            ], [
                'items.required' => 'Data items tidak boleh kosong',
                'items.array' => 'Data items harus berupa array',
                'items.min' => 'Minimal harus ada 1 item',
                'items.*.sku.required' => 'SKU tidak boleh kosong',
                'items.*.quantity.required' => 'Quantity tidak boleh kosong',
                'items.*.quantity.integer' => 'Quantity harus berupa angka',
                'items.*.quantity.min' => 'Quantity minimal 1',
                'items.*.serials.required' => 'Nomor seri tidak boleh kosong',
                'items.*.serials.array' => 'Nomor seri harus berupa array',
                'items.*.serials.min' => 'Minimal harus ada 1 nomor seri',
                'items.*.serials.*.required' => 'Nomor seri tidak boleh kosong',
                'items.*.serials.*.string' => 'Nomor seri harus berupa string',
                'items.*.serials.*.min' => 'Nomor seri minimal 1 karakter',
                'items.*.serials.*.max' => 'Nomor seri maksimal 255 karakter',
                'items.max' => 'Maksimal 1000 items per request',
                'items.*.quantity.max' => 'Quantity maksimal 10000',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        }

        $order = $this->findOrderByTracking($trackingNumber, ['items', 'items.serials']);

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if ($order->is_packed) {
            return response()->json([
                'message' => 'Order ini sudah berstatus packed dan tidak bisa divalidasi ulang.'
            ], 422);
        }

        $expectedItems = $order->items->keyBy('sku');

        // Validasi semua item terlebih dahulu
        foreach ($request->items as $item) {

            $sku = $item['sku'];
            $qty = $item['quantity'];
            $serials = $item['serials'];

            if (!isset($expectedItems[$sku])) {
                return response()->json([
                    'message' => "SKU {$sku} tidak ada dalam order"
                ], 422);
            }

            if ($expectedItems[$sku]->quantity != $qty) {
                return response()->json([
                    'message' => "Quantity SKU {$sku} tidak cocok. Diharapkan {$expectedItems[$sku]->quantity}, tapi scan {$qty}"
                ], 422);
            }

            if (count($serials) != $qty) {
                return response()->json([
                    'message' => "Jumlah nomor seri SKU {$sku} harus {$qty} buah"
                ], 422);
            }


            if (empty($serials)) {
                return response()->json([
                    'message' => "Nomor seri SKU {$sku} tidak boleh kosong"
                ], 422);
            }
        }

        // OPTIMASI: Ambil semua SKU dan Stok sekaligus (batch query)
        $skuList = array_column($request->items, 'sku');
        $skuModels = Sku::whereIn('sku', $skuList)
            ->get()
            ->keyBy('sku');

        $skuIds = $skuModels->pluck('id')->toArray();

        // Lakukan semua operasi dalam transaction
        try {
            DB::transaction(function () use ($request, $expectedItems, $order, $skuModels) {
                $allSerialsToInsert = [];
                $now = now();

                foreach ($request->items as $item) {
                    $sku = $item['sku'];
                    $serials = $item['serials'];

                    // Hapus serial lama
                    OrderItemSerial::where('order_item_id', $expectedItems[$sku]->id)->delete();

                    // Prepare batch insert untuk serial numbers
                    foreach ($serials as $serial) {
                        $allSerialsToInsert[] = [
                            'order_item_id' => $expectedItems[$sku]->id,
                            'serial_number' => $serial,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    // Kurangi stok gudang produk dengan lock untuk mencegah race condition
                    $skuModel = $skuModels[$sku] ?? null;

                    if ($skuModel) {
                        $workspaceEntry = GudangProdukWorkspaceStockEntry::where('sku_id', $skuModel->id)
                            ->where('qty', '>', 0)
                            ->lockForUpdate()
                            ->first();

                        // Jika tidak ada stok di workspace, lanjutkan saja
                        // (barang belum di-input ke sistem gudang, bukan error)
                        if (!$workspaceEntry) {
                            continue;
                        }

                        $availableWorkspaceQty = (int) $workspaceEntry->qty;
                        $requiredQty = (int) $item['quantity'];

                        // Stok ada tapi kurang — ini baru jadi error
                        if ($availableWorkspaceQty < $requiredQty) {
                            throw new \Exception("Stok gudang produk untuk SKU {$sku} tidak mencukupi. Stok tersedia: {$availableWorkspaceQty}, dibutuhkan: {$requiredQty}");
                        }

                        $deductQty = $requiredQty;
                        $slotId = $workspaceEntry->slot_id;
                        $workspaceEntry->qty -= $deductQty;

                        if ($workspaceEntry->qty <= 0) {
                            $workspaceEntry->delete();
                        } else {
                            $workspaceEntry->save();
                        }

                        GudangProdukActivityLog::create([
                            'type' => 'packing_out',
                            'sku_id' => $skuModel->id,
                            'from_slot_id' => $slotId,
                            'to_slot_id' => null,
                            'qty' => $deductQty,
                            'notes' => "Packing order #{$order->order_number} - SKU: {$sku}",
                            'created_by' => Auth::id(),
                        ]);
                    }
                }

                // Batch insert semua serial numbers sekaligus (lebih cepat)
                if (!empty($allSerialsToInsert)) {
                    // Chunk insert untuk menghindari query terlalu besar
                    $chunks = array_chunk($allSerialsToInsert, 500);
                    foreach ($chunks as $chunk) {
                        OrderItemSerial::insert($chunk);
                    }
                }

                // Update status order
                $order->update(['is_packed' => 1]);

                // Buat log
                OrderLog::create([
                    'order_id'     => $order->id,
                    'action'       => 'scan_validasi',
                    'performed_by' => Auth::user()->name ?? 'System',
                    'notes'        => 'Order berhasil discan dan divalidasi',
                ]);
            });

            return response()->json([
                'message' => 'Order berhasil divalidasi',
                'order' => $order->fresh(['items', 'items.serials'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    private function findOrderByTracking($trackingNumber, array $relations = [])
    {
        $normalizedTrackingNumber = $this->normalizeTrackingNumber($trackingNumber);

        if ($normalizedTrackingNumber === '') {
            return null;
        }

        $query = Order::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $order = (clone $query)
            ->where('tracking_number', $normalizedTrackingNumber)
            ->first();

        if ($order) {
            return $order;
        }

        return (clone $query)
            ->whereNotNull('tracking_number')
            ->whereRaw('TRIM(tracking_number) = ?', [$normalizedTrackingNumber])
            ->first();
    }

    private function normalizeTrackingNumber($trackingNumber): string
    {
        return trim(urldecode((string) $trackingNumber));
    }

    private function normalizeLogPerPage($perPage): int
    {
        $allowedValues = [25, 50, 100];
        $normalized = (int) $perPage;

        return in_array($normalized, $allowedValues, true) ? $normalized : 25;
    }

    public function getAllLogs(Request $request)
    {
        $filters = app(PackingLogReportService::class)->prepareFilters(
            $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode'])
        );

        $logs = app(PackingLogReportService::class)->paginateLogs(
            $filters,
            $this->normalizeLogPerPage($request->input('per_page', 25)),
            $request->input('cursor')
        );

        return response()->json($logs);
    }

    public function getLogDetail(string $sourceType, int $sourceId)
    {
        $detail = app(PackingLogReportService::class)->getDetail($sourceType, $sourceId);

        return response()->json([
            'message' => 'Detail log berhasil diambil',
            'data' => $detail,
        ]);
    }

    public function getSummaryReport(Request $request)
    {
        $rawFilters = $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode']);

        $rawFilters['start_date'] = $rawFilters['start_date'] ?? now()->toDateString();
        $rawFilters['end_date'] = $rawFilters['end_date'] ?? now()->toDateString();

        $summary = app(PackingLogReportService::class)->getSummary(
            app(PackingLogReportService::class)->prepareFilters($rawFilters)
        );

        return response()->json(array_merge([
            'message' => 'Summary report berhasil diambil',
        ], $summary));
    }

    public function exportLogsToExcel(Request $request)
    {
        $filters = app(PackingLogReportService::class)->prepareFilters(
            $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode'])
        );

        $fileName = 'packing_logs_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new OrderLogsExport($filters), $fileName);
    }

    public function requestLogsExport(Request $request)
    {
        $filters = app(PackingLogReportService::class)->prepareFilters(
            $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode'])
        );

        $timestamp = now()->format('Ymd_His');
        $fileName = 'packing_logs_' . $timestamp . '.xlsx';

        $exportRequest = PackingLogExport::create([
            'user_id' => Auth::id(),
            'status' => 'queued',
            'filters' => [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date'],
                'status' => $filters['status'],
                'tracking_number' => $filters['tracking_number'],
                'performed_by' => $filters['performed_by'],
                'mode' => $filters['mode'],
            ],
            'file_name' => $fileName,
            'file_path' => 'exports/packing-logs/' . $fileName,
        ]);

        GeneratePackingLogExport::dispatch($exportRequest->id);
        $exportRequest->refresh();

        return response()->json([
            'message' => 'Export logs sedang diproses',
            'data' => $this->formatExportResponse($exportRequest),
        ], $exportRequest->status === 'completed' ? 200 : 202);
    }

    public function showLogsExport(int $exportId)
    {
        $exportRequest = $this->findUserExportOrFail($exportId);

        return response()->json([
            'message' => 'Status export berhasil diambil',
            'data' => $this->formatExportResponse($exportRequest),
        ]);
    }

    public function downloadLogsExport(int $exportId)
    {
        $exportRequest = $this->findUserExportOrFail($exportId);

        if ($exportRequest->status !== 'completed' || !$exportRequest->file_path) {
            return response()->json([
                'message' => 'File export belum siap diunduh',
            ], 422);
        }

        if (!Storage::disk('public')->exists($exportRequest->file_path)) {
            return response()->json([
                'message' => 'File export tidak ditemukan',
            ], 404);
        }

        return Storage::disk('public')->download($exportRequest->file_path, $exportRequest->file_name);
    }

    private function findUserExportOrFail(int $exportId): PackingLogExport
    {
        return PackingLogExport::where('id', $exportId)
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }

    private function formatExportResponse(PackingLogExport $exportRequest): array
    {
        return [
            'id' => $exportRequest->id,
            'status' => $exportRequest->status,
            'file_name' => $exportRequest->file_name,
            'error_message' => $exportRequest->error_message,
            'created_at' => optional($exportRequest->created_at)->toDateTimeString(),
            'started_at' => optional($exportRequest->started_at)->toDateTimeString(),
            'completed_at' => optional($exportRequest->completed_at)->toDateTimeString(),
            'can_download' => $exportRequest->status === 'completed' && !empty($exportRequest->file_path),
        ];
    }

    public function pickingQueue()
    {
        $orders = Order::where('label_print_status', 'printed')
            ->whereNull('picked_at')
            ->orderBy('label_print_time', 'asc')
            ->get();

        return response()->json($orders);
    }

    public function markPicked($id)
    {
        $order = Order::findOrFail($id);

        $order->update([
            'picked_at' => now()
        ]);

        return response()->json(['message' => 'Order marked as picked']);
    }

    public function batchMarkPicked(Request $request)
    {
        $limit = $request->input('limit', 1);

        // 1. Ambil N order teratas yang belum dipick
        $orders = Order::where('label_print_status', 'printed')
            ->whereNull('picked_at')
            ->orderBy('label_print_time', 'asc')
            ->take($limit)
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Tidak ada orderan untuk diproses'], 404);
        }

        // 2. Update picked_at
        Order::whereIn('id', $orders->pluck('id'))->update([
            'picked_at' => now()
        ]);

        // 3. Hitung Summary SKU
        $summary = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $sku = $item->sku;
                $qty = $item->quantity;

                if (isset($summary[$sku])) {
                    $summary[$sku] += $qty;
                } else {
                    $summary[$sku] = $qty;
                }
            }
        }

        // Sort summary by key (SKU name)
        ksort($summary);

        // 4. Return data untuk PDF
        return response()->json([
            'message' => count($orders) . ' orderan, berhasil diproses',
            'processed_orders' => $orders->pluck('order_number'),
            'summary' => $summary,
            'timestamp' => now()->format('d-m-Y H:i:s')
        ]);
    }
}
