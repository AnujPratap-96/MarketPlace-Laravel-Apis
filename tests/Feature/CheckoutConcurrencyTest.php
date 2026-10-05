<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $vendorUser;
    private Vendor $vendor;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $this->vendorUser = User::factory()->create(['role' => UserRole::VENDOR]);

        $this->vendor = Vendor::create([
            'user_id' => $this->vendorUser->id,
            'store_name' => 'Apex Electronics',
            'slug' => 'apex-electronics',
            'commission_rate' => 10.00,
            'balance_in_cents' => 0,
            'is_verified' => true,
        ]);

        $category = Category::create(['name' => 'Hardware', 'slug' => 'hardware']);

        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Mechanical Numpad',
            'slug' => 'mechanical-numpad',
            'price_in_cents' => 5000, // $50.00
            'stock' => 5,
            'is_active' => true,
        ]);
    }

    public function test_checkout_successfully_locks_and_decrements_stock(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/v1/orders/checkout', [
            'shipping_address' => '221B Baker Street, London',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('order.total_amount_cents', 10000)
            ->assertJsonPath('order.status', 'pending');

        // Verify stock decremented under lock: 5 - 2 = 3
        $this->assertEquals(3, $this->product->fresh()->stock);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'total_amount_in_cents' => 10000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'payout_status' => PayoutStatus::HELD_IN_ESCROW->value,
        ]);
    }

    public function test_checkout_fails_atomically_when_requested_quantity_exceeds_stock(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/v1/orders/checkout', [
            'shipping_address' => '221B Baker Street, London',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10, // Stock is only 5!
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_STOCK');

        // Verify stock remains untouched at 5
        $this->assertEquals(5, $this->product->fresh()->stock);

        // Verify no order was created
        $this->assertDatabaseEmpty('orders');
    }

    public function test_admin_delivery_settles_escrow_to_vendor_wallet_with_commission(): void
    {
        // 1. Create paid order with 1 item of $100 ($10,000 cents)
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD-TEST-1234',
            'total_amount_in_cents' => 10000,
            'status' => OrderStatus::PAID,
            'shipping_address' => 'Test Address',
        ]);

        $order->items()->create([
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'unit_price_in_cents' => 10000,
            'quantity' => 1,
            'subtotal_in_cents' => 10000,
            'payout_status' => PayoutStatus::HELD_IN_ESCROW,
        ]);

        // 2. Admin calls deliver endpoint
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/deliver");

        $response->assertStatus(200)
            ->assertJsonPath('order.status', 'delivered');

        // Vendor commission rate is 10%
        // Commission = 1,000 cents ($10.00). Vendor payout = 9,000 cents ($90.00)
        $this->assertEquals(9000, $this->vendor->fresh()->balance_in_cents);

        // Verify ledger transactions
        $this->assertDatabaseHas('transactions', [
            'vendor_id' => $this->vendor->id,
            'type' => TransactionType::ESCROW_RELEASE->value,
            'amount_in_cents' => 9000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'vendor_id' => $this->vendor->id,
            'type' => TransactionType::COMMISSION_DEDUCTED->value,
            'amount_in_cents' => -1000,
        ]);
    }
}
