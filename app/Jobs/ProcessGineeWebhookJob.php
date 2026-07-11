<?php

namespace App\Jobs;

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

    /**
     * The order ID to sync.
     *
     * @var string
     */
    protected $orderId;

    /**
     * Create a new job instance.
     *
     * @param string $orderId
     */
    public function __construct(string $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     *
     * @param GineeOrderService $service
     * @return void
     */
    public function handle(GineeOrderService $service)
    {
        Log::info("ProcessGineeWebhookJob started for Order ID: {$this->orderId}");

        try {
            $result = $service->syncOrderByIds([$this->orderId]);

            Log::info("ProcessGineeWebhookJob completed for Order ID: {$this->orderId}", [
                'processed' => $result['totalProcessed'] ?? 0,
                'new' => $result['new'] ?? 0,
                'updated' => $result['updated'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error("ProcessGineeWebhookJob failed for Order ID: {$this->orderId}. Error: {$e->getMessage()}", [
                'exception' => $e
            ]);
            throw $e;
        }
    }
}
