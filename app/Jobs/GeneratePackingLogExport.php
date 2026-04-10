<?php

namespace App\Jobs;

use App\Exports\OrderLogsExport;
use App\Models\PackingLogExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use Throwable;

class GeneratePackingLogExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $exportId;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function handle(): void
    {
        $exportRequest = PackingLogExport::find($this->exportId);

        if (!$exportRequest) {
            return;
        }

        $exportRequest->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ]);

        if ($exportRequest->file_path && Storage::disk('public')->exists($exportRequest->file_path)) {
            Storage::disk('public')->delete($exportRequest->file_path);
        }

        try {
            (new OrderLogsExport($exportRequest->filters ?? []))
                ->store($exportRequest->file_path, 'public', Excel::XLSX);
        } catch (Throwable $exception) {
            if (!$this->shouldIgnoreTemporaryFileCleanupFailure($exception, $exportRequest->file_path)) {
                throw $exception;
            }

            Log::warning('Packing log export completed with temporary file cleanup warning', [
                'export_id' => $exportRequest->id,
                'file_path' => $exportRequest->file_path,
                'error' => $exception->getMessage(),
            ]);
        }

        $exportRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $exportRequest = PackingLogExport::find($this->exportId);

        if (!$exportRequest) {
            return;
        }

        $exportRequest->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }

    private function shouldIgnoreTemporaryFileCleanupFailure(Throwable $exception, ?string $filePath): bool
    {
        $message = $exception->getMessage();

        if (
            !$filePath ||
            !Storage::disk('public')->exists($filePath) ||
            stripos($message, 'unlink(') === false ||
            stripos($message, 'laravel-excel') === false ||
            stripos($message, 'Permission denied') === false
        ) {
            return false;
        }

        $temporaryFilePath = $this->extractTemporaryFilePath($message);

        if ($temporaryFilePath && is_file($temporaryFilePath)) {
            clearstatcache(true, $temporaryFilePath);
            usleep(250000);
            @unlink($temporaryFilePath);
            clearstatcache(true, $temporaryFilePath);
        }

        return true;
    }

    private function extractTemporaryFilePath(string $message): ?string
    {
        if (!preg_match('/^unlink\((.+)\): Permission denied$/i', $message, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }
}
