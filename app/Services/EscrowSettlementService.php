<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class EscrowSettlementService
{
    /**
     * Settles escrow payouts to individual vendors upon verified order delivery.
     *
     * @param Order $order
     * @return Order
     */
    public function settleOrderEscrow(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order->load('items.vendor');

            foreach ($order->items as $item) {
                if ($item->payout_status !== PayoutStatus::HELD_IN_ESCROW) {
                    continue; // Skip already settled or refunded items
                }

                /** @var Vendor $vendor */
                $vendor = Vendor::where('id', $item->vendor_id)->lockForUpdate()->firstOrFail();

                // 1. Calculate platform fee split
                $commissionCents = (int) round($item->subtotal_in_cents * ($vendor->commission_rate / 100));
                $vendorEarningsCents = $item->subtotal_in_cents - $commissionCents;

                // 2. Atomically credit vendor wallet
                $vendor->increment('balance_in_cents', $vendorEarningsCents);

                // 3. Mark line item as settled
                $item->update(['payout_status' => PayoutStatus::SETTLED]);

                // 4. Record double-entry financial ledger records
                Transaction::create([
                    'order_id' => $order->id,
                    'vendor_id' => $vendor->id,
                    'type' => TransactionType::ESCROW_RELEASE,
                    'amount_in_cents' => $vendorEarningsCents,
                    'idempotency_key' => "escrow-item-{$item->id}-vendor-credit",
                    'notes' => "Escrow payout for Order #{$order->order_number} Item #{$item->id}",
                ]);

                Transaction::create([
                    'order_id' => $order->id,
                    'vendor_id' => $vendor->id,
                    'type' => TransactionType::COMMISSION_DEDUCTED,
                    'amount_in_cents' => -$commissionCents,
                    'idempotency_key' => "escrow-item-{$item->id}-commission",
                    'notes' => "Platform commission deduction ({$vendor->commission_rate}%)",
                ]);
            }

            $order->update(['status' => OrderStatus::DELIVERED]);

            return $order->fresh(['items', 'transactions']);
        });
    }
}
