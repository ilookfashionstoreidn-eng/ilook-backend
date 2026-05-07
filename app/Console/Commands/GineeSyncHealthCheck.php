<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GineeSyncHealthCheck extends Command
{
    protected $signature = 'ginee:health-check
        {--hot-max-minutes=6 : Maksimal keterlambatan sync sukses packing hot}
        {--active-pool-max-minutes=20 : Maksimal keterlambatan sync sukses READY_TO_SHIP pool 3 hari}
        {--deep-pool-max-minutes=120 : Maksimal keterlambatan sync sukses READY_TO_SHIP pool 30 hari}
        {--repair90-max-minutes=1560 : Maksimal keterlambatan scheduler repair 90 hari}';

    protected $description = 'Cek kesehatan robot sinkronisasi Ginee';

    public function handle(): int
    {
        $now = now();
        $hasCriticalIssue = false;

        $this->info('Ginee Sync Health Check');
        $this->line('Waktu sekarang: ' . $now->format('Y-m-d H:i:s'));
        $this->line('Lock sinkronisasi: ' . $this->getLockStatus());
        $this->newLine();

        $this->info('Schedule Runner');

        $scheduleChecks = [
            [
                'label' => 'ginee:sync-packing-hot',
                'command' => 'ginee:sync-packing-hot',
                'maxMinutes' => (int) $this->option('hot-max-minutes'),
            ],
            [
                'label' => 'ginee:sync-ready-to-ship-pool --days=3',
                'command' => 'ginee:sync-ready-to-ship-pool --days=3',
                'maxMinutes' => (int) $this->option('active-pool-max-minutes'),
            ],
            [
                'label' => 'ginee:sync-ready-to-ship-pool --days=30',
                'command' => 'ginee:sync-ready-to-ship-pool --days=30',
                'maxMinutes' => (int) $this->option('deep-pool-max-minutes'),
            ],
            [
                'label' => 'ginee:sync-daily-repair 90',
                'command' => 'ginee:sync-daily-repair 90',
                'maxMinutes' => (int) $this->option('repair90-max-minutes'),
            ],
        ];

        foreach ($scheduleChecks as $check) {
            $result = $this->checkScheduleRun($check['command'], $check['maxMinutes'], $now);
            $this->renderResult($check['label'], $result);
            $hasCriticalIssue = $hasCriticalIssue || $result['severity'] === 'CRITICAL';
        }

        $this->newLine();
        $this->info('Sync Success Log');

        $syncChecks = [
            [
                'label' => 'orders_packing_hot',
                'type' => 'orders_packing_hot',
                'maxMinutes' => (int) $this->option('hot-max-minutes'),
            ],
            [
                'label' => 'orders_ready_to_ship_pool_3d',
                'type' => 'orders_ready_to_ship_pool_3d',
                'maxMinutes' => (int) $this->option('active-pool-max-minutes'),
            ],
            [
                'label' => 'orders_ready_to_ship_pool_30d',
                'type' => 'orders_ready_to_ship_pool_30d',
                'maxMinutes' => (int) $this->option('deep-pool-max-minutes'),
            ],
        ];

        foreach ($syncChecks as $check) {
            $result = $this->checkSyncLog($check['type'], $check['maxMinutes'], $now);
            $this->renderResult($check['label'], $result);
            $hasCriticalIssue = $hasCriticalIssue || $result['severity'] === 'CRITICAL';
        }

        $this->newLine();
        $this->line('Catatan: bagian Schedule Runner menunjukkan command terakhir terlihat dijalankan scheduler.');
        $this->line('Catatan: bagian Sync Success Log menunjukkan sync terakhir yang sukses update sync_logs.');

        return $hasCriticalIssue ? self::FAILURE : self::SUCCESS;
    }

    private function getLockStatus(): string
    {
        $locks = [
            'hot' => 'ginee-hot-sync-lock',
            'active_pool' => 'ginee-active-pool-lock',
            'repair' => 'ginee-repair-sync-lock',
        ];

        $statuses = [];

        foreach ($locks as $label => $lockName) {
            $lock = Cache::lock($lockName, 1);

            if ($lock->get()) {
                optional($lock)->release();
                $statuses[] = "{$label}=FREE";

                continue;
            }

            $statuses[] = "{$label}=LOCKED";
        }

        return implode(', ', $statuses);
    }

    private function checkScheduleRun(string $command, int $maxMinutes, Carbon $now): array
    {
        $lastRunAt = $this->findLastScheduleRun($command);

        if (!$lastRunAt) {
            return [
                'severity' => 'CRITICAL',
                'message' => 'tidak ditemukan di schedule.log',
            ];
        }

        return $this->buildTimedResult(
            $lastRunAt,
            $maxMinutes,
            $now,
            'last run scheduler'
        );
    }

    private function checkSyncLog(string $type, int $maxMinutes, Carbon $now): array
    {
        $syncLog = SyncLog::query()->where('type', $type)->first();

        if (!$syncLog || !$syncLog->last_sync_at) {
            return [
                'severity' => 'CRITICAL',
                'message' => 'belum ada row sync_logs',
            ];
        }

        return $this->buildTimedResult(
            Carbon::parse($syncLog->last_sync_at),
            $maxMinutes,
            $now,
            'last sync sukses'
        );
    }

    private function buildTimedResult(Carbon $timestamp, int $maxMinutes, Carbon $now, string $prefix): array
    {
        $diffMinutes = $timestamp->diffInMinutes($now);
        $severity = $diffMinutes > $maxMinutes ? 'CRITICAL' : 'OK';

        return [
            'severity' => $severity,
            'message' => sprintf(
                '%s %s (%d menit lalu, batas %d menit)',
                $prefix,
                $timestamp->format('Y-m-d H:i:s'),
                $diffMinutes,
                $maxMinutes
            ),
        ];
    }

    private function renderResult(string $label, array $result): void
    {
        $line = sprintf('[%s] %s - %s', $result['severity'], $label, $result['message']);

        if ($result['severity'] === 'CRITICAL') {
            $this->error($line);

            return;
        }

        $this->line($line);
    }

    private function findLastScheduleRun(string $command): ?Carbon
    {
        $scheduleLogPath = storage_path('logs/schedule.log');

        if (!is_file($scheduleLogPath)) {
            return null;
        }

        $contents = $this->readLogTail($scheduleLogPath, 5 * 1024 * 1024);

        if ($contents === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = $lines[$index];

            if (
                !str_contains($line, 'Running scheduled command:')
                || !$this->lineContainsCommand($line, $command)
            ) {
                continue;
            }

            if (!preg_match('/^\[([^\]]+)\]/', $line, $matches)) {
                continue;
            }

            return Carbon::parse($matches[1]);
        }

        return null;
    }

    private function lineContainsCommand(string $line, string $command): bool
    {
        foreach (preg_split('/\s+/', trim($command)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }

            if (!preg_match('/' . preg_quote($token, '/') . '(?!\d)/', $line)) {
                return false;
            }
        }

        return true;
    }

    private function readLogTail(string $path, int $maxBytes): string
    {
        $size = filesize($path);

        if ($size === false || $size <= 0) {
            return '';
        }

        $handle = fopen($path, 'rb');

        if (!$handle) {
            return '';
        }

        $bytesToRead = min($size, $maxBytes);

        if ($size > $bytesToRead) {
            fseek($handle, -$bytesToRead, SEEK_END);
        }

        $contents = fread($handle, $bytesToRead) ?: '';
        fclose($handle);

        return $contents;
    }
}
