<?php

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Idempotent ledger of processed payment webhook events.
 *
 * @property int $id
 * @property string $provider
 * @property string $provider_event_id
 * @property string $event_type
 * @property int|null $order_id
 * @property array<string, mixed> $payload
 * @property Carbon $processed_at
 */
class PaymentEvent extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'provider_event_id',
        'event_type',
        'order_id',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
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
