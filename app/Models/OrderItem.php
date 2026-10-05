<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'vendor_id',
        'product_id',
        'unit_price_in_cents',
        'quantity',
        'subtotal_in_cents',
        'payout_status',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_in_cents' => 'integer',
            'quantity' => 'integer',
            'subtotal_in_cents' => 'integer',
            'payout_status' => PayoutStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
