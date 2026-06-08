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

            $seriTersisa = $this->getRemainingSerialsForSlot($skuId, $slotId, $qtySisa);

            if (in_array($normalizedSerial, $seriTersisa, true)) {
                return $slotId;
            }
        }

        return null;
    }

    /**
     * Mengambil daftar kode seri yang masih ada di slot tertentu untuk SKU tertentu.
     * Menggunakan riwayat masuk (placement/mutation) dikurangi riwayat keluar (mutation/packing_out).
     *
     * @param int $skuId
     * @param string $slotId
     * @param int $qtySisa
     * @return array
     */
    protected function getRemainingSerialsForSlot(int $skuId, string $slotId, int $qtySisa): array
    {
        // Ambil semua activity logs terkait SKU + slot ini (baik masuk maupun keluar)
        $logs = DB::table('gudang_produk_activity_logs')
            ->where('sku_id', $skuId)
            ->where(function ($query) use ($slotId) {
                $query->where('to_slot_id', $slotId)
                      ->orWhere('from_slot_id', $slotId);
            })
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get(['type', 'from_slot_id', 'to_slot_id', 'notes']);

        $enteredSerials = [];
        $exitedSerials = [];

        foreach ($logs as $log) {
            $notes = (string) ($log->notes ?? '');
            $type = $log->type;

            $serialsInLog = $this->extractSerialsFromNotes($notes);
            if (empty($serialsInLog)) {
                continue;
            }

            // Tentukan apakah log ini masuk ke slot atau keluar dari slot
            $isIncoming = ($log->to_slot_id === $slotId && in_array($type, ['placement', 'mutation']));
            $isOutgoing = ($log->from_slot_id === $slotId && in_array($type, ['mutation', 'packing_out']));

            if ($isIncoming) {
                foreach ($serialsInLog as $s) {
                    $enteredSerials[] = $s;
                }
            }
            if ($isOutgoing) {
                foreach ($serialsInLog as $s) {
                    $exitedSerials[] = $s;
                }
            }
        }

        // Hitung selisih: enteredSerials - exitedSerials dengan memperhitungkan frekuensi
        $exitedCounts = array_count_values($exitedSerials);
        $remainingSerials = [];

        foreach ($enteredSerials as $serial) {
            if (isset($exitedCounts[$serial]) && $exitedCounts[$serial] > 0) {
                $exitedCounts[$serial]--;
            } else {
                $remainingSerials[] = $serial;
            }
        }

        // Unify & sort: Urutan dari yang terbaru ke terlama (reverse agar terbaru di atas)
        $seen = [];
        $uniqueRemaining = [];
        foreach (array_reverse($remainingSerials) as $serial) {
            if (!isset($seen[$serial])) {
                $seen[$serial] = true;
                $uniqueRemaining[] = $serial;
            }
        }

        // Jika quantity sisa di stock entry kurang dari jumlah serial unik yang tersisa,
        // kita batasi hasilnya agar sesuai qtySisa.
        if ($qtySisa > 0 && count($uniqueRemaining) > $qtySisa) {
            $uniqueRemaining = array_slice($uniqueRemaining, 0, $qtySisa);
        }

        return $uniqueRemaining;
    }

    /**
     * Mengekstrak kode seri dari activity log notes.
     *
     * @param string $notes
     * @return array
     */
    protected function extractSerialsFromNotes(string $notes): array
    {
        $serials = [];
        $notes = trim($notes);
        if ($notes === '') {
            return [];
        }

        $rawSeri = '';

        // Cari salah satu prefix: Barcode:, Kode seri:, atau Seri:
        if (preg_match('/(?:Barcode|Kode\s+seri|Seri):\s*(.+)$/i', $notes, $matches)) {
            $rawSeri = trim($matches[1]);

            // Jika ada metadata di belakang seperti "| Selisih...", "| Sesi...", dll.
            if (preg_match('/^(.*?)\s*\|\s*(Selisih|Sesi|Packing|Scan|Inject|Order|Kode\s+seri|Barcode|Seri)/i', $rawSeri, $subMatches)) {
                $rawSeri = trim($subMatches[1]);
            }
        }

        if ($rawSeri === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $rawSeri)));
        foreach ($parts as $part) {
            if ($part !== '') {
                if (str_contains($part, '|')) {
                    $subParts = array_map('trim', explode('|', $part));
                    $serials[] = strtoupper(end($subParts));
                } else {
                    $serials[] = strtoupper($part);
                }
            }
        }

        return $serials;
    }
}
