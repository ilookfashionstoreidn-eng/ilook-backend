<?php

namespace App\Http\Controllers;

use App\Models\NoDataGineeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackingNoDataGineeController extends Controller
{
    /**
     * Submit scanned tracking numbers that may or may not exist in Ginee.
     * No order validation is performed — only the tracking number and scanner name are recorded.
     */
    /**
     * Check if a tracking number has already been scanned before.
     */
    public function check($trackingNumber)
    {
        $normalized = $this->normalizeTrackingNumber($trackingNumber);

        if ($normalized === '') {
            return response()->json([
                'message' => 'Tracking number tidak boleh kosong',
            ], 422);
        }

        $exists = NoDataGineeLog::where('tracking_number', $normalized)->exists();

        if ($exists) {
            return response()->json([
                'message' => "Tracking number {$normalized} sudah pernah discan sebelumnya",
                'already_scanned' => true,
            ], 409);
        }

        return response()->json([
            'message' => 'OK',
            'already_scanned' => false,
        ]);
    }

    /**
     * Submit scanned tracking numbers that may or may not exist in Ginee.
     * No order validation is performed — only the tracking number and scanner name are recorded.
     */
    public function submit(Request $request)
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

                foreach ($trackingNumbers as $trackingNumber) {
                    // Block duplicate tracking numbers that were already submitted before
                    $alreadyExists = NoDataGineeLog::where('tracking_number', $trackingNumber)
                        ->lockForUpdate()
                        ->exists();

                    if ($alreadyExists) {
                        throw new \RuntimeException(
                            "Tracking number {$trackingNumber} sudah pernah discan sebelumnya"
                        );
                    }

                    // Try to find the order, but it's OK if it doesn't exist
                    $order = $this->findOrderByTracking($trackingNumber);

                    NoDataGineeLog::create([
                        'tracking_number' => $trackingNumber,
                        'scanner_name' => $scannerName,
                        'order_id' => $order?->id,
                        'notes' => $order
                            ? "Tracking {$trackingNumber} dicatat via No Data Ginee (order #{$order->order_number} ditemukan)"
                            : "Tracking {$trackingNumber} dicatat via No Data Ginee (data order tidak ada di Ginee)",
                    ]);

                    $logged[] = [
                        'tracking_number' => $trackingNumber,
                        'order_found' => $order !== null,
                        'order_number' => $order?->order_number,
                    ];
                }

                return $logged;
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

        return response()->json([
            'message' => 'Semua tracking number berhasil dicatat melalui No Data Ginee',
            'processed_count' => count($result),
            'items' => $result,
        ]);
    }

    private function findOrderByTracking(string $trackingNumber)
    {
        if ($trackingNumber === '') {
            return null;
        }

        $order = \App\Models\Order::where('tracking_number', $trackingNumber)->first();

        if ($order) {
            return $order;
        }

        return \App\Models\Order::whereNotNull('tracking_number')
            ->whereRaw('TRIM(tracking_number) = ?', [$trackingNumber])
            ->first();
    }

    private function normalizeTrackingNumber($trackingNumber): string
    {
        return trim(urldecode((string) $trackingNumber));
    }
}
