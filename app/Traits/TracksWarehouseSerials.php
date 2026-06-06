<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait TracksWarehouseSerials
{
    /**
     * Menemukan slot_id tempat serial number berada untuk SKU tertentu.
     * Jika tidak ditemukan di slot manapun yang masih memiliki qty > 0, return null.
     *
     * @param int $skuId
     * @param string $serialNumber
     * @return string|null
     */
    protected function findSlotForSerial(int $skuId, string $serialNumber): ?string
    {
        $normalizedSerial = strtoupper(trim($serialNumber));
        if ($normalizedSerial === '') {
            return null;
        }

        // Ambil semua stock entries untuk SKU ini yang qty > 0
        $stockEntries = DB::table('gudang_produk_stock_entries')
            ->where('sku_id', $skuId)
            ->where('qty', '>', 0)
            ->get(['slot_id', 'qty']);

        foreach ($stockEntries as $entry) {
            $slotId = $entry->slot_id;
            $qtySisa = (int) $entry->qty;

            // Ambil semua placement logs untuk SKU + slot ini
            $logs = DB::table('gudang_produk_activity_logs')
                ->where('sku_id', $skuId)
                ->where('to_slot_id', $slotId)
                ->where('type', 'placement')
                ->whereNotNull('notes')
                ->orderBy('created_at', 'asc')
                ->get(['notes']);

            $seriList = [];
            foreach ($logs as $log) {
                $notes = (string) $log->notes;
                if (preg_match('/Kode seri:\s*(.+?)(?:\s*\|.*)?$/i', $notes, $matches)) {
                    $rawSeri = trim($matches[1]);
                    $parts = array_filter(array_map('trim', explode(',', $rawSeri)));
                    foreach ($parts as $part) {
                        if ($part !== '') {
                            $seriList[] = strtoupper(trim($part));
                        }
                    }
                } elseif (preg_match('/Seri:\s*(.+?)(?:\s*\|.*)?$/i', $notes, $matches)) {
                    $rawSeri = trim($matches[1]);
                    $parts = array_filter(array_map('trim', explode(',', $rawSeri)));
                    foreach ($parts as $part) {
                        if ($part !== '') {
                            $seriList[] = strtoupper(trim($part));
                        }
                    }
                }
            }

            // Deduplicate (preserve order, keep last occurrence)
            $seen = [];
            $uniqueSeri = [];
            foreach (array_reverse($seriList) as $kode) {
                if (!isset($seen[$kode])) {
                    $seen[$kode] = true;
                    $uniqueSeri[] = $kode;
                }
            }

            // Seri yang tersisa di slot ini (FIFO: terbaru sejumlah qtySisa)
            $seriTersisa = array_slice($uniqueSeri, 0, $qtySisa);

            if (in_array($normalizedSerial, $seriTersisa, true)) {
                return $slotId;
            }
        }

        return null;
    }
}
