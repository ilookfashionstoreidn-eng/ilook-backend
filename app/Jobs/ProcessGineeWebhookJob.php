<?php

namespace App\Jobs;

use App\Models\Order;
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
            $result = $service->syncOrderByIds([$this->orderId]);

            Log::info("ProcessGineeWebhookJob completed for Order ID: {$this->orderId}", [
                'processed' => $result['totalProcessed'] ?? 0,
                'new'       => $result['new'] ?? 0,
                'updated'   => $result['updated'] ?? 0,
            ]);

            // Update webhook log: status = processed, hubungkan ke order
            if ($this->webhookLogId) {
                $order = Order::where('ginee_order_id', $this->orderId)
                    ->orWhere('order_number', $this->orderId)
                    ->first();

                // Cari order yang baru saja disync berdasarkan order_number/ginee id
                // (syncOrderByIds menggunakan externalOrderSn sebagai order_number)
                WebhookLog::where('id', $this->webhookLogId)->update([
                    'status'   => 'processed',
                    'order_id' => $order?->id,
                ]);
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
