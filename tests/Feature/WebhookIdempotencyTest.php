<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;
    private string $secret = 'test_webhook_secret_key';

    protected function setUp(): void
    {
        parent::setUp();

        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-HOOK-999',
            'total_amount_in_cents' => 5000,
            'status' => OrderStatus::PENDING,
            'shipping_address' => 'Test Address',
        ]);
    }

    public function test_valid_webhook_marks_order_paid_and_records_log(): void
    {
        $payloadData = [
            'id' => 'evt_test_123456',
            'type' => 'payment.captured',
            'order_number' => $this->order->order_number,
            'payment_id' => 'pay_abc123',
        ];

        $rawBody = json_encode($payloadData);
        $signature = hash_hmac('sha256', $rawBody, $this->secret);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            ],
            $rawBody
        );

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertEquals(OrderStatus::PAID, $this->order->fresh()->status);
        $this->assertEquals('pay_abc123', $this->order->fresh()->payment_reference);

        $this->assertDatabaseHas('webhook_logs', [
            'event_id' => 'evt_test_123456',
        ]);
    }

    public function test_duplicate_webhook_is_idempotently_skipped(): void
    {
        $payloadData = [
            'id' => 'evt_duplicate_test',
            'type' => 'payment.captured',
            'order_number' => $this->order->order_number,
            'payment_id' => 'pay_duplicate_1',
        ];

        $rawBody = json_encode($payloadData);
        $signature = hash_hmac('sha256', $rawBody, $this->secret);

        // First call
        $this->call(
            'POST',
            '/api/v1/webhooks/payment',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => $signature],
            $rawBody
        )->assertStatus(200)->assertJsonPath('status', 'success');

        // Replay of exact same webhook (Simulating network retry)
        $secondResponse = $this->call(
            'POST',
            '/api/v1/webhooks/payment',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => $signature],
            $rawBody
        );

        $secondResponse->assertStatus(200)
            ->assertJsonPath('status', 'skipped');

        // Verify only 1 log entry exists (no duplicate)
        $this->assertEquals(1, \App\Models\WebhookLog::where('event_id', 'evt_duplicate_test')->count());
    }

    public function test_invalid_signature_is_rejected_with_400(): void
    {
        $payloadData = [
            'id' => 'evt_hacker_test',
            'type' => 'payment.captured',
        ];

        $rawBody = json_encode($payloadData);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WEBHOOK_SIGNATURE' => 'fake_invalid_signature_hex',
            ],
            $rawBody
        );

        $response->assertStatus(400)
            ->assertJsonPath('error', 'INVALID_SIGNATURE');

        $this->assertEquals(OrderStatus::PENDING, $this->order->fresh()->status);
    }
}
