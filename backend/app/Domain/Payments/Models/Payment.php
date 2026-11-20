<?php

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $provider
 * @property string|null $provider_reference
 * @property string|null $checkout_url
 * @property string $status
 * @property string $amount
 * @property string $currency
 * @property CarbonInterface|null $paid_at
 */
class Payment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'provider',
        'provider_reference',
        'checkout_url',
        'status',
        'amount',
        'currency',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
