<?php

use App\Models\NoDataGineeLog;
use App\Services\NoDataGineeSerialPackingService;

$service = app(NoDataGineeSerialPackingService::class);

$logs = NoDataGineeLog::where('scan_mode', 'serial_scan')->get();

$processed = 0;
foreach ($logs as $log) {
    try {
        $service->deductStockForScans($log);
        $processed++;
        echo "Processed log ID: {$log->id}\n";
    } catch (\Exception $e) {
        echo "Error processing log ID {$log->id}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone processing {$processed} logs.\n";
