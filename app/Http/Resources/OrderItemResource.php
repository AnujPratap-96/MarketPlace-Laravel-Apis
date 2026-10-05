<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'vendor_id' => $this->vendor_id,
            'vendor_store' => $this->vendor?->store_name,
            'unit_price_cents' => $this->unit_price_in_cents,
            'formatted_unit_price' => '$' . number_format($this->unit_price_in_cents / 100, 2),
            'quantity' => $this->quantity,
            'subtotal_cents' => $this->subtotal_in_cents,
            'formatted_subtotal' => '$' . number_format($this->subtotal_in_cents / 100, 2),
            'payout_status' => $this->payout_status->value ?? $this->payout_status,
        ];
    }
}
