<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'total_amount_cents' => $this->total_amount_in_cents,
            'formatted_total' => '$' . number_format($this->total_amount_in_cents / 100, 2),
            'status' => $this->status->value ?? $this->status,
            'payment_reference' => $this->payment_reference,
            'shipping_address' => $this->shipping_address,
            'created_at' => $this->created_at->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
