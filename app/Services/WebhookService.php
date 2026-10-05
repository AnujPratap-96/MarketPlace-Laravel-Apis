<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WebhookService
{
    /**
     * Verifies HMAC signature and idempotently processes payment gateway webhooks.
     *
     * @param string $rawPayload
     * @param string $signature
     * @param string $secret
     * @param array $eventData
     * @return array{status: string, message: string}
     */
    public function handlePaymentWebhook(
        string $rawPayload,
        string $signature,
        string $secret,
        array $eventData
    ): array {
        // 1. Verify cryptographic HMAC-SHA256 signature using timing-safe comparison
        $expectedSignature = hash_hmac('sha256', $rawPayload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new BadRequestHttpException('Invalid webhook signature verification.');
        }

        $eventId = $eventData['id'] ?? $eventData['event_id'] ?? null;
        if (!$eventId) {
            throw new BadRequestHttpException('Missing event identifier in webhook payload.');
        }

        // 2. Idempotency Check: Protect against duplicate webhooks from gateway retries
        $alreadyProcessed = WebhookLog::where('event_id', $eventId)->exists();
        if ($alreadyProcessed) {
            return [
                'status' => 'skipped',
                'message' => 'Duplicate webhook event already processed.',
            ];
        }

        // 3. Atomically log event and transition order state
        DB::transaction(function () use ($eventId, $eventData) {
            WebhookLog::create([
                'event_id' => $eventId,
                'gateway' => 'payment_gateway',
                'event_type' => $eventData['type'] ?? 'payment.captured',
                'payload' => $eventData,
                'processed_at' => now(),
            ]);

            $orderNumber = $eventData['order_number'] ?? null;
            if ($orderNumber) {
                $order = Order::where('order_number', $orderNumber)->first();
                if ($order && $order->status === OrderStatus::PENDING) {
                    $order->update([
                        'status' => OrderStatus::PAID,
                        'payment_reference' => $eventData['payment_id'] ?? $eventId,
                    ]);
                }
            }
        });

        return [
            'status' => 'success',
            'message' => 'Payment webhook processed and order updated to paid.',
        ];
    }
}
