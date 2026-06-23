<?php

namespace App\Http\Controllers;

use App\Models\NoDataGineeLog;
use App\Models\Order;
use App\Services\GudangProdukPackingStockService;
use App\Services\NoDataGineeSerialPackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackingNoDataGineeController extends Controller
{
    public function __construct(
        private NoDataGineeSerialPackingService $serialPackingService,
        private GudangProdukPackingStockService $stockService
    ) {
    }

    public function check($trackingNumber)
    {
        $normalized = $this->normalizeTrackingNumber($trackingNumber);

        if ($normalized === '') {
            return response()->json([
                'message' => 'Tracking number tidak boleh kosong',
            ], 422);
        }

        $existingLog = NoDataGineeLog::query()
            ->whereRaw('TRIM(tracking_number) = ?', [$normalized])
            ->latest('id')
            ->first();
        $order = $this->findOrderByTracking($normalized);

        if ($existingLog) {
            return response()->json([
                'message' => "Tracking number {$normalized} sudah pernah discan sebelumnya",
                'already_scanned' => true,
                'scan_mode' => $existingLog->scan_mode,
                'reconciliation_status' => $existingLog->reconciliation_status,
                'reconciled_at' => optional($existingLog->reconciled_at)->toDateTimeString(),
                'order_found' => $order !== null,
                'order_already_available' => $order !== null,
                'order' => $this->formatOrderPreview($order),
            ], 409);
        }

        if ($order) {
            if ($order->is_packed) {
                return response()->json([
                    'message' => "Tracking number {$normalized} sudah memiliki data order Ginee dan sudah dipacking sebelumnya",
                    'already_scanned' => true,
                    'order_found' => true,
                    'order_already_available' => true,
                    'order' => $this->formatOrderPreview($order),
                ], 409);
            }

            return response()->json([
                'message' => "Order #{$order->order_number} ditemukan di sistem dan belum dipacking.",
                'already_scanned' => false,
                'order_found' => true,
                'order_already_available' => true,
                'order' => $this->formatOrderPreview($order),
            ]);
        }

        return response()->json([
            'message' => 'OK',
            'already_scanned' => false,
            'order_found' => $order !== null,
            'order_already_available' => false,
            'order' => $this->formatOrderPreview($order),
        ]);
    }

    public function submit(Request $request)
    {
        $scanMode = strtolower(trim((string) $request->input('scan_mode', 'tracking_only')));

        if ($scanMode === 'serial_scan') {
            return $this->submitSerialScan($request);
        }

        return $this->submitTrackingOnly($request);
    }

    private function submitTrackingOnly(Request $request)
    {
        try {
            $request->validate([
                'scanner_name' => 'required|string|min:1|max:255',
                'tracking_numbers' => 'required|array|min:1|max:1000',
                'tracking_numbers.*' => 'required|string|min:1|max:255',
            ], [
                'scanner_name.required' => 'Nama scanner wajib diisi',
                'scanner_name.string' => 'Nama scanner harus berupa teks',
                'tracking_numbers.required' => 'Daftar tracking number wajib diisi',
                'tracking_numbers.array' => 'Daftar tracking number harus berupa array',
                'tracking_numbers.min' => 'Minimal harus ada 1 tracking number',
                'tracking_numbers.max' => 'Maksimal 1000 tracking number per request',
                'tracking_numbers.*.required' => 'Tracking number tidak boleh kosong',
                'tracking_numbers.*.string' => 'Tracking number harus berupa teks',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors(),
            ], 422);
        }

        $scannerName = trim((string) $request->input('scanner_name'));
        $trackingNumbers = collect($request->input('tracking_numbers', []))
            ->map(fn ($trackingNumber) => $this->normalizeTrackingNumber($trackingNumber))
            ->values();

        if ($trackingNumbers->contains(fn ($trackingNumber) => $trackingNumber === '')) {
            return response()->json([
                'message' => 'Tracking number tidak boleh kosong',
            ], 422);
        }

        $trackingCounts = $trackingNumbers->countBy();
        $duplicateValue = $trackingCounts->search(fn ($count) => $count > 1);

        if ($duplicateValue !== false) {
            return response()->json([
                'message' => "Tracking number {$duplicateValue} terdeteksi duplikat dalam sesi ini",
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($trackingNumbers, $scannerName) {
                $logged = [];
                $totalSummary = $this->stockService->emptySummary();

                foreach ($trackingNumbers as $trackingNumber) {
                    $alreadyExists = NoDataGineeLog::query()
                        ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
                        ->lockForUpdate()
                        ->exists();

                    if ($alreadyExists) {
                        throw new \RuntimeException(
                            "Tracking number {$trackingNumber} sudah pernah discan sebelumnya"
                        );
                    }

                    $order = $this->findOrderByTrackingForUpdate($trackingNumber);

                    if ($order) {
                        if ($order->is_packed) {
                            throw new \RuntimeException(
                                "Tracking number {$trackingNumber} sudah berstatus packed"
                            );
                        }

                        // Deduct stock immediately!
                        $deduction = $this->stockService->deductOrderStock(
                            $order,
                            "Packing No Data Ginee tracking only order #{$order->order_number}",
                            true
                        );
                        $order->update(['is_packed' => 1]);

                        NoDataGineeLog::create([
                            'tracking_number' => $trackingNumber,
                            'scanner_name' => $scannerName,
                            'scan_mode' => 'tracking_only',
                            'order_id' => $order->id,
                            'reconciliation_status' => 'reconciled',
                            'reconciled_at' => now(),
                            'notes' => $this->buildTrackingOnlyNotes($trackingNumber, $order, $deduction, true),
                        ]);

                        $logged[] = [
                            'tracking_number' => $trackingNumber,
                            'order_found' => true,
                            'order_number' => $order->order_number,
                            'order_marked_packed' => true,
                            'deduction' => $deduction,
                        ];

                        $totalSummary = $this->stockService->mergeSummary($totalSummary, $deduction['summary']);
                        continue;
                    }

                    NoDataGineeLog::create([
                        'tracking_number' => $trackingNumber,
                        'scanner_name' => $scannerName,
                        'scan_mode' => 'tracking_only',
                        'order_id' => null,
                        'notes' => $this->buildTrackingOnlyNotes($trackingNumber, null, null, false),
                    ]);

                    $logged[] = [
                        'tracking_number' => $trackingNumber,
                        'order_found' => false,
                        'order_number' => null,
                        'order_marked_packed' => false,
                        'deduction' => null,
                    ];
                }

                return [
                    'items' => $logged,
                    'summary' => $totalSummary,
                ];
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses scan No Data Ginee',
            ], 500);
        }

        $message = 'Semua tracking number berhasil dicatat melalui No Data Ginee';
        $summaryText = $this->stockService->buildSummaryText($result['summary']);

        if ($summaryText !== '') {
            $message .= ". {$summaryText}";
        }

        return response()->json([
            'message' => $message,
            'processed_count' => count($result['items']),
            'summary' => $result['summary'],
            'items' => $result['items'],
        ]);
    }

    private function submitSerialScan(Request $request)
    {
        try {
            $request->validate([
                'scanner_name' => 'required|string|min:1|max:255',
                'tracking_number' => 'required|string|min:1|max:255',
                'items' => 'required|array|min:1|max:1000',
                'items.*.actual_sku' => 'required|string|min:1|max:255',
                'items.*.serial_number' => 'required|string|min:1|max:255',
            ], [
                'scanner_name.required' => 'Nama scanner wajib diisi',
                'tracking_number.required' => 'Tracking number wajib diisi',
                'items.required' => 'Data scan serial wajib diisi',
                'items.array' => 'Data scan serial harus berupa array',
                'items.min' => 'Minimal harus ada 1 scan serial',
                'items.max' => 'Maksimal 1000 scan serial per request',
                'items.*.actual_sku.required' => 'SKU hasil scan tidak boleh kosong',
                'items.*.serial_number.required' => 'Nomor seri tidak boleh kosong',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors(),
            ], 422);
        }

        $scannerName = trim((string) $request->input('scanner_name'));
        $trackingNumber = $this->normalizeTrackingNumber($request->input('tracking_number'));
        $items = collect($request->input('items', []))
            ->values()
            ->map(function ($item) {
                return [
                    'actual_sku' => trim((string) ($item['actual_sku'] ?? '')),
                    'serial_number' => trim((string) ($item['serial_number'] ?? '')),
                ];
            });

        if ($trackingNumber === '') {
            return response()->json([
                'message' => 'Tracking number tidak boleh kosong',
            ], 422);
        }

        if ($items->contains(fn ($item) => $item['actual_sku'] === '' || $item['serial_number'] === '')) {
            return response()->json([
                'message' => 'SKU dan nomor seri wajib diisi untuk setiap scan',
            ], 422);
        }

        $serialCounts = $items->pluck('serial_number')->countBy();
        $duplicateSerial = $serialCounts->search(fn ($count) => $count > 1);

        if ($duplicateSerial !== false) {
            return response()->json([
                'message' => "Nomor seri {$duplicateSerial} terdeteksi duplikat dalam sesi ini",
            ], 422);
        }

        $usedSerialMessage = $this->getUsedSerialMessage($items->all());
        if ($usedSerialMessage) {
            return response()->json([
                'message' => $usedSerialMessage,
            ], 422);
        }

        $order = $this->findOrderByTracking($trackingNumber);

        if ($order && $order->is_packed) {
            return response()->json([
                'message' => "Tracking number {$trackingNumber} sudah memiliki data order Ginee dan sudah dipacking sebelumnya",
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($trackingNumber, $scannerName, $items) {
                $existingLog = NoDataGineeLog::query()
                    ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
                    ->lockForUpdate()
                    ->first();

                if ($existingLog) {
                    throw new \RuntimeException(
                        "Tracking number {$trackingNumber} sudah pernah discan sebelumnya"
                    );
                }

                $order = $this->findOrderByTrackingForUpdate($trackingNumber);

                if ($order && $order->is_packed) {
                    throw new \RuntimeException(
                        "Tracking number {$trackingNumber} sudah berstatus packed"
                    );
                }

                $usedSerialMessage = $this->getUsedSerialMessage($items->all());
                if ($usedSerialMessage) {
                    throw new \RuntimeException($usedSerialMessage);
                }

                $log = NoDataGineeLog::create([
                    'tracking_number' => $trackingNumber,
                    'scanner_name' => $scannerName,
                    'scan_mode' => 'serial_scan',
                    'reconciliation_status' => $order ? 'pending_reconciliation' : 'pending_order',
                    'order_id' => $order ? $order->id : null,
                    'notes' => $order
                        ? "Tracking {$trackingNumber} dicatat via No Data Ginee serial scan untuk order #{$order->order_number}"
                        : "Tracking {$trackingNumber} dicatat via No Data Ginee serial scan dan menunggu data order Ginee masuk",
                ]);

                $this->serialPackingService->attachScans($log, $items->all());
                $this->serialPackingService->deductStockForScans($log);

                return $this->serialPackingService->reconcileLog($log);
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses scan serial No Data Ginee',
            ], 500);
        }

        $message = 'Tracking number dan nomor seri berhasil dicatat melalui No Data Ginee serial scan.';

        if ($result['reconciliation_status'] === 'reconciled') {
            $message = 'Tracking number dan nomor seri berhasil dicatat lalu langsung direkonsiliasi ke order.';
        } elseif ($result['reconciliation_status'] === 'needs_review') {
            $message = 'Tracking number dan nomor seri berhasil dicatat, tetapi hasilnya masih perlu review manual setelah order ditemukan.';
        } elseif ($result['reconciliation_status'] === 'failed') {
            $message = 'Tracking number dan nomor seri berhasil dicatat, tetapi rekonsiliasi otomatis gagal.';
        }

        return response()->json([
            'message' => $message,
            'tracking_number' => $trackingNumber,
            'scan_mode' => 'serial_scan',
            'item_count' => $items->count(),
            'reconciliation_status' => $result['reconciliation_status'],
            'reconciled_at' => $result['reconciled_at'],
            'order_found' => $result['order_found'],
            'order' => $result['order'],
            'summary' => $result['summary'],
            'notes' => $result['notes'],
        ]);
    }

    private function findOrderByTracking(string $trackingNumber): ?Order
    {
        if ($trackingNumber === '') {
            return null;
        }

        $order = Order::where('tracking_number', $trackingNumber)->first();

        if ($order) {
            return $order;
        }

        return Order::whereNotNull('tracking_number')
            ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
            ->first();
    }

    private function findOrderByTrackingForUpdate(string $trackingNumber): ?Order
    {
        if ($trackingNumber === '') {
            return null;
        }

        $query = Order::with('items')->lockForUpdate();

        $order = (clone $query)
            ->where('tracking_number', $trackingNumber)
            ->first();

        if ($order) {
            return $order;
        }

        return (clone $query)
            ->whereNotNull('tracking_number')
            ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
            ->first();
    }

    private function normalizeTrackingNumber($trackingNumber): string
    {
        return trim(urldecode((string) $trackingNumber));
    }

    private function normalizeSerialNumber($serialNumber): string
    {
        return strtoupper(trim((string) $serialNumber));
    }

    private function getUsedSerialMessage(array $items): ?string
    {
        return null;
    }

    private function collectSerialLookup(array $items): array
    {
        $serials = [];

        foreach ($items as $item) {
            $itemSerials = $item['serials'] ?? [$item['serial_number'] ?? null];

            foreach ($itemSerials as $serial) {
                $normalizedSerial = $this->normalizeSerialNumber($serial);

                if ($normalizedSerial !== '') {
                    $serials[$normalizedSerial] = $serial;
                }
            }
        }

        return $serials;
    }

    private function formatUsedSerialMessage($serial, $sku = null, $trackingNumber = null, $orderNumber = null): string
    {
        $context = [];

        if ($sku) {
            $context[] = "SKU {$sku}";
        }

        if ($trackingNumber) {
            $context[] = "tracking {$trackingNumber}";
        } elseif ($orderNumber) {
            $context[] = "order {$orderNumber}";
        }

        $suffix = empty($context) ? '' : ' di ' . implode(', ', $context);

        return "Nomor seri {$serial} sudah pernah digunakan{$suffix} dan tidak bisa digunakan lagi.";
    }

    private function buildOrderAlreadyAvailableMessage(string $trackingNumber, Order $order): string
    {
        $orderNumber = trim((string) $order->order_number);
        $orderLabel = $orderNumber !== '' ? " (#{$orderNumber})" : '';

        return "Tracking number {$trackingNumber} sudah memiliki data order Ginee{$orderLabel} dan tidak boleh discan melalui No Data Ginee. Gunakan alur packing normal.";
    }

    private function buildTrackingOnlyNotes(
        string $trackingNumber,
        ?Order $order,
        ?array $deduction,
        bool $orderMarkedPacked
    ): string
    {
        if (!$order) {
            return "Tracking {$trackingNumber} dicatat via No Data Ginee (data order tidak ada di Ginee)";
        }

        $notes = [
            $orderMarkedPacked
                ? "Tracking {$trackingNumber} dicatat via No Data Ginee (order #{$order->order_number} ditemukan dan ditandai packed)"
                : "Tracking {$trackingNumber} dicatat via No Data Ginee (order #{$order->order_number} ditemukan, tetapi item order belum tersedia)",
        ];

        $summaryText = $deduction ? $this->stockService->buildSummaryText($deduction['summary']) : '';

        if ($summaryText !== '') {
            $notes[] = $summaryText;
        }

        return implode('. ', $notes);
    }

    private function formatOrderPreview(?Order $order): ?array
    {
        if (!$order) {
            return null;
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'total_qty' => (int) ($order->total_qty ?? 0),
            'is_packed' => (bool) ($order->is_packed ?? false),
        ];
    }
}
