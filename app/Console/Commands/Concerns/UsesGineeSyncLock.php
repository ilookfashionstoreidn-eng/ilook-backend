<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\Cache;

trait UsesGineeSyncLock
{
    protected function runWithGineeSyncLock(callable $callback, int $seconds = 14400): int
    {
        $lock = Cache::lock('ginee-sync-lock', $seconds);

        if (!$lock->get()) {
            $this->warn('Proses sinkronisasi Ginee lain masih berjalan. Tunggu sampai selesai lalu coba lagi.');

            return self::SUCCESS;
        }

        try {
            return $callback();
        } finally {
            optional($lock)->release();
        }
    }
}
