<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    /**
     * Executes atomic checkout with pessimistic inventory locking (SELECT ... FOR UPDATE).
     *
     * @param User $customer
     * @param array<array{product_id: int, quantity: int}> $items
     * @param string $shippingAddress
     * @return Order
     * @throws InsufficientStockException
     */
    public function checkout(User $customer, array $items, string $shippingAddress): Order
    {
        // 1. Sort items by product_id deterministically to PREVENT DEADLOCKS across concurrent transactions
        usort($items, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

        return DB::transaction(function () use ($customer, $items, $shippingAddress) {
            $totalAmountInCents = 0;
            $orderItemsToCreate = [];

            // 2. Iterate and lock each product row using lockForUpdate()
            foreach ($items as $item) {
                /** @var Product $product */
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // 3. Concurrency check: Ensure inventory sufficiency under exclusive row lock
                if ($product->stock < $item['quantity']) {
                    throw new InsufficientStockException($product->name, $product->stock);
                }

                // 4. Atomically decrement stock
                $product->decrement('stock', $item['quantity']);

                $subtotal = $product->price_in_cents * $item['quantity'];
                $totalAmountInCents += $subtotal;

                $orderItemsToCreate[] = [
                    'vendor_id' => $product->vendor_id,
                    'product_id' => $product->id,
                    'unit_price_in_cents' => $product->price_in_cents,
                    'quantity' => $item['quantity'],
                    'subtotal_in_cents' => $subtotal,
                    'payout_status' => PayoutStatus::HELD_IN_ESCROW,
                ];
            }

            // 5. Create Order header
            $order = Order::create([
                'customer_id' => $customer->id,
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'total_amount_in_cents' => $totalAmountInCents,
                'status' => OrderStatus::PENDING,
                'shipping_address' => $shippingAddress,
            ]);

            // 6. Bulk insert order line items
            foreach ($orderItemsToCreate as $itemData) {
                $order->items()->create($itemData);
            }

            return $order->load(['items.product', 'items.vendor']);
        });
    }
}
