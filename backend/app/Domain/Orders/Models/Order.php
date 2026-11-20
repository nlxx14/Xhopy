<?php

namespace App\Domain\Orders\Models;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $order_number
 * @property int $user_id
 * @property int|null $store_id
 * @property string|null $idempotency_key
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property string $payment_method
 * @property string $currency
 * @property string $subtotal_amount
 * @property string $discount_amount
 * @property string $shipping_amount
 * @property string $total_amount
 * @property string $shipping_recipient_name
 * @property string $shipping_phone
 * @property string $shipping_line_1
 * @property string|null $shipping_line_2
 * @property string|null $shipping_barangay
 * @property string $shipping_city
 * @property string $shipping_province
 * @property string $shipping_postal_code
 * @property string $shipping_country_code
 * @property string|null $shipping_notes
 * @property CarbonInterface $placed_at
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $cancelled_at
 * @property-read User $user
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderStatusHistory> $statusHistories
 * @property-read Payment|null $latestPayment
 */
class Order extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'user_id',
        'store_id',
        'idempotency_key',
        'status',
        'payment_status',
        'payment_method',
        'currency',
        'subtotal_amount',
        'discount_amount',
        'shipping_amount',
        'total_amount',
        'shipping_recipient_name',
        'shipping_phone',
        'shipping_line_1',
        'shipping_line_2',
        'shipping_barangay',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'shipping_country_code',
        'shipping_notes',
        'placed_at',
        'paid_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('occurred_at')->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('id');
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }
}
