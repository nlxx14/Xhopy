<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable ledger entry for an inventory stock movement.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $order_id
 * @property int|null $actor_id
 * @property StockMovementType $type
 * @property int $quantity_delta
 * @property int $previous_quantity
 * @property int $resulting_quantity
 * @property string|null $reason
 * @property CarbonInterface $occurred_at
 * @property-read Product|null $product
 * @property-read User|null $actor
 * @property-read Order|null $order
 */
class StockMovement extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'order_id',
        'actor_id',
        'type',
        'quantity_delta',
        'previous_quantity',
        'resulting_quantity',
        'reason',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity_delta' => 'integer',
            'previous_quantity' => 'integer',
            'resulting_quantity' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
