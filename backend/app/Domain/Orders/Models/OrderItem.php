<?php

namespace App\Domain\Orders\Models;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable line-item snapshot captured at order placement.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $store_id
 * @property string $product_name
 * @property string $product_slug
 * @property string $sku
 * @property string|null $selected_color
 * @property string|null $selected_size
 * @property string $unit_price
 * @property string $currency
 * @property int $quantity
 * @property string $line_total
 */
class OrderItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'store_id',
        'product_name',
        'product_slug',
        'sku',
        'selected_color',
        'selected_size',
        'unit_price',
        'currency',
        'quantity',
        'line_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
