<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessGineeWebhookJob;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Log;

class GineeWebhookController extends Controller
{
    /**
     * Handle incoming webhook requests from Ginee.
     */
    public function handleOrderWebhook(Request $request)
    {
        $rawPayload = $request->getContent();
        $payload    = $request->all();

        Log::info('Ginee Webhook received payload', [
            'headers' => $request->headers->all(),
            'payload' => $payload,
        ]);

        // 1. Signature Verification (configurable)
        $shouldVerify = filter_var(env('GINEE_WEBHOOK_VERIFY_SIGNATURE', true), FILTER_VALIDATE_BOOLEAN);
        if ($shouldVerify) {
            $signature = $request->header('X-Advai-Signature') ?? $request->header('Authorization');
            if (empty($signature)) {
                Log::warning('Ginee Webhook: Missing signature header');
                return response()->json(['code' => 'ERROR_MISSING_SIGNATURE', 'message' => 'Missing signature header'], 401);
            }

            if (!$this->isValidSignature($rawPayload, $signature)) {
                Log::warning('Ginee Webhook: Invalid signature', ['signature_received' => $signature]);
                return response()->json(['code' => 'ERROR_INVALID_SIGNATURE', 'message' => 'Invalid signature'], 401);
            }
        }

        // 2. Validate and Extract Order ID
        $entity  = $payload['entity'] ?? null;
        $action  = $payload['action'] ?? null;

        if ($entity && strtolower($entity) !== 'order') {
            Log::info("Ginee Webhook ignored entity type: {$entity}");
            return response()->json(['code' => 'SUCCESS', 'message' => 'Ignored entity type'], 200);
        }

        $orderId = $payload['payload']['orderId'] ?? $payload['orderId'] ?? null;
        if (empty($orderId)) {
            Log::warning('Ginee Webhook: Order ID not found in payload', ['payload' => $payload]);
            return response()->json(['code' => 'ERROR_MISSING_ORDER_ID', 'message' => 'Order ID not found in payload'], 400);
        }

        // 3. Simpan log webhook ke database
        $webhookLog = WebhookLog::create([
            'ginee_order_id' => $orderId,
            'entity'         => $entity ?? 'order',
            'action'         => $action,
            'status'         => 'received',
            'raw_payload'    => $payload,
        ]);

        // 4. Dispatch background job (kirim webhook_log_id agar bisa diupdate)
        ProcessGineeWebhookJob::dispatch($orderId, $webhookLog->id);

        return response()->json([
            'code'     => 'SUCCESS',
            'message'  => 'Webhook received and job dispatched successfully',
            'order_id' => $orderId,
        ], 200);
    }

    /**
     * Validate Ginee webhook signature.
     */
    private function isValidSignature(string $rawPayload, string $signature): bool
    {
        $secretKey = env('GINEE_SECRET_KEY');
        if (empty($secretKey)) {
            Log::error('Ginee Webhook verification failed: GINEE_SECRET_KEY is not configured in .env');
            return false;
        }

        if (str_contains($signature, ':')) {
            $parts     = explode(':', $signature, 2);
            $signature = $parts[1];
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $rawPayload, $secretKey, true));

        return hash_equals($expectedSignature, $signature);
    }
}
