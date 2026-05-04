<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GineeSyncHealthCheck extends Command
{
    protected $signature = 'ginee:health-check
        {--orders-max-minutes=15 : Maksimal keterlambatan sync sukses orders}
        {--printed-max-minutes=10 : Maksimal keterlambatan sync sukses orders_printed}
        {--printed-repair-max-minutes=90 : Maksimal keterlambatan sync sukses orders_printed_repair}
        {--repair14-max-minutes=1560 : Maksimal keterlambatan scheduler repair 14 hari}
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
                'label' => 'ginee:sync-orders',
                'command' => 'ginee:sync-orders',
                'maxMinutes' => (int) $this->option('orders-max-minutes'),
            ],
            [
                'label' => 'ginee:sync-printed-orders',
                'command' => 'ginee:sync-printed-orders',
                'maxMinutes' => (int) $this->option('printed-max-minutes'),
            ],
            [
                'label' => 'ginee:sync-printed-repair',
                'command' => 'ginee:sync-printed-repair',
                'maxMinutes' => (int) $this->option('printed-repair-max-minutes'),
            ],
            [
                'label' => 'ginee:sync-daily-repair 14',
                'command' => 'ginee:sync-daily-repair 14',
                'maxMinutes' => (int) $this->option('repair14-max-minutes'),
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
                'label' => 'orders',
                'type' => 'orders',
                'maxMinutes' => (int) $this->option('orders-max-minutes'),
            ],
            [
                'label' => 'orders_printed',
                'type' => 'orders_printed',
                'maxMinutes' => (int) $this->option('printed-max-minutes'),
            ],
            [
                'label' => 'orders_printed_repair',
                'type' => 'orders_printed_repair',
                'maxMinutes' => (int) $this->option('printed-repair-max-minutes'),
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
        $lock = Cache::lock('ginee-sync-lock', 1);

        if ($lock->get()) {
            optional($lock)->release();

            return 'FREE';
        }

        return 'LOCKED (ada proses sync lain yang sedang berjalan)';
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
                || !str_contains($line, $command)
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
