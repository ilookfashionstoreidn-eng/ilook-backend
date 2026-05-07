<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\Cache;

trait UsesGineeSyncLock
{
    protected function runWithGineeSyncLock(
        callable $callback,
        int $seconds = 600,
        string $lockName = 'ginee-sync-lock'
    ): int {
        $lock = Cache::lock($lockName, $seconds);

        if (!$lock->get()) {
            $this->warn("Proses sinkronisasi Ginee lain masih berjalan pada lock {$lockName}.");

            return self::SUCCESS;
        }

        $hotSyncMarker = $lockName === 'ginee-hot-sync-lock' ? 'ginee-hot-sync-running' : null;

        if ($hotSyncMarker) {
            Cache::put($hotSyncMarker, true, $seconds);
        }

        try {
            return $callback();
        } finally {
            if ($hotSyncMarker) {
                Cache::forget($hotSyncMarker);
            }

            optional($lock)->release();
        }
    }
}
