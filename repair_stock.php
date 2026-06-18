<?php

use Illuminate\Support\Facades\DB;
use App\Models\GudangProdukWorkspaceStockEntry;
use App\Models\GudangProdukLayout;
use App\Models\GudangProdukActivityLog;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::transaction(function () {
    echo "Starting stock correction...\n";

    // Find layout id mapping
    $layouts = GudangProdukLayout::all()->pluck('id', 'uid');

    // Delete all existing stock entries safely
    DB::table('gudang_produk_stock_entries')->truncate();

    // Grouping logic for incoming and outgoing quantities
    $activityLogs = DB::table('gudang_produk_activity_logs')->get();

    $stockMap = [];

    foreach ($activityLogs as $log) {
        $skuId = $log->sku_id;
        $qty = (int) $log->qty;

        // INCOMING
        if (in_array($log->type, ['placement', 'mutation']) && !empty($log->to_slot_id)) {
            $slotId = $log->to_slot_id;
            $key = "{$skuId}_{$slotId}";
            if (!isset($stockMap[$key])) {
                $stockMap[$key] = [
                    'sku_id' => $skuId,
                    'slot_id' => $slotId,
                    'qty' => 0
                ];
            }
            $stockMap[$key]['qty'] += $qty;
        }

        // OUTGOING
        if (in_array($log->type, ['packing_out', 'mutation']) && !empty($log->from_slot_id)) {
            $slotId = $log->from_slot_id;
            $key = "{$skuId}_{$slotId}";
            if (!isset($stockMap[$key])) {
                $stockMap[$key] = [
                    'sku_id' => $skuId,
                    'slot_id' => $slotId,
                    'qty' => 0
                ];
            }
            $stockMap[$key]['qty'] -= $qty;
        }
    }

    $inserts = [];
    $now = now();

    foreach ($stockMap as $entry) {
        if ($entry['qty'] > 0) {
            // Find layout ID from slot string e.g., layout_icffsr_mnsjivce__F3...
            preg_match('/^(layout_[a-z0-9_]+)__/', $entry['slot_id'], $matches);
            $layoutUid = $matches[1] ?? 'layout_icffsr_mnsjivce';
            $layoutId = $layouts[$layoutUid] ?? 1;

            $inserts[] = [
                'layout_id' => $layoutId,
                'sku_id' => $entry['sku_id'],
                'slot_id' => $entry['slot_id'],
                'qty' => $entry['qty'],
                'updated_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    // Chunk inserts to avoid query size limits
    foreach (array_chunk($inserts, 500) as $chunk) {
        DB::table('gudang_produk_stock_entries')->insert($chunk);
    }

    echo "Stock correction completed successfully! " . count($inserts) . " valid stock entries recreated.\n";
});

