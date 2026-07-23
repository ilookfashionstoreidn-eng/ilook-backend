<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\Services\GineeOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGineeWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $orderId;
    protected ?int   $webhookLogId;

    public function __construct(string $orderId, ?int $webhookLogId = null)
    {
        $this->orderId      = $orderId;
        $this->webhookLogId = $webhookLogId;
    }

    public function handle(GineeOrderService $service)
    {
        Log::info("ProcessGineeWebhookJob started for Order ID: {$this->orderId}");

        try {
            $result = $service->syncOrderByIds([$this->orderId], 'webhook');

            Log::info("ProcessGineeWebhookJob completed for Order ID: {$this->orderId}", [
                'processed' => $result['totalProcessed'] ?? 0,
                'new'       => $result['new'] ?? 0,
                'updated'   => $result['updated'] ?? 0,
            ]);

            // Update webhook log: status = processed, hubungkan ke order jika bisa ditemukan
            if ($this->webhookLogId) {
                try {
                    // Gunakan order_id dari result syncOrderByIds (lebih akurat dari query manual)
                    $orderId = $result['order_ids'][0] ?? null;

                    WebhookLog::where('id', $this->webhookLogId)->update([
                        'status'   => 'processed',
                        'order_id' => $orderId,
                    ]);
                } catch (\Throwable $linkError) {
                    // Jika update gagal, tetap tandai processed
                    WebhookLog::where('id', $this->webhookLogId)->update([
                        'status' => 'processed',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error("ProcessGineeWebhookJob failed for Order ID: {$this->orderId}. Error: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            // Update webhook log: status = failed
            if ($this->webhookLogId) {
                WebhookLog::where('id', $this->webhookLogId)->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
