<?php

namespace App\Domain\Orders\Support;

use App\Domain\Catalog\Support\Money;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderStatusHistory;

final class OrderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'orderNumber' => $order->order_number,
            'status' => $order->status->value,
            'statusLabel' => $order->status->label(),
            'paymentStatus' => $order->payment_status->value,
            'paymentStatusLabel' => $order->payment_status->label(),
            'paymentMethod' => $order->payment_method,
            'currency' => $order->currency,
            'totalFormatted' => Money::format($order->total_amount, $order->currency),
            'itemCount' => (int) $order->items->sum('quantity'),
            'placedAtFormatted' => $order->placed_at->toFormattedDateString(),
            'placedAtDiff' => $order->placed_at->diffForHumans(),
            'canCancel' => $order->status->canBeCancelledByCustomer(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Order $order): array
    {
        $latestPayment = $order->latestPayment;

        return [
            ...$this->summary($order),
            'subtotalFormatted' => Money::format($order->subtotal_amount, $order->currency),
            'discountFormatted' => Money::format($order->discount_amount, $order->currency),
            'shippingFormatted' => Money::format($order->shipping_amount, $order->currency),
            'shippingAddress' => [
                'recipientName' => $order->shipping_recipient_name,
                'phone' => $order->shipping_phone,
                'line1' => $order->shipping_line_1,
                'line2' => $order->shipping_line_2,
                'barangay' => $order->shipping_barangay,
                'city' => $order->shipping_city,
                'province' => $order->shipping_province,
                'postalCode' => $order->shipping_postal_code,
                'countryCode' => $order->shipping_country_code,
                'notes' => $order->shipping_notes,
                'formattedSummary' => implode(', ', array_filter([
                    $order->shipping_line_1,
                    $order->shipping_line_2,
                    $order->shipping_barangay,
                    $order->shipping_city,
                    $order->shipping_province.' '.$order->shipping_postal_code,
                ])),
            ],
            'items' => array_values(
                $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'productId' => $item->product_id,
                    'productName' => $item->product_name,
                    'productSlug' => $item->product_slug,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unitPriceFormatted' => Money::format($item->unit_price, $item->currency),
                    'lineTotalFormatted' => Money::format($item->line_total, $item->currency),
                ])->all(),
            ),
            'timeline' => array_values(
                $order->statusHistories->map(fn (OrderStatusHistory $entry): array => [
                    'id' => $entry->id,
                    'fromStatus' => $entry->from_status,
                    'toStatus' => $entry->to_status,
                    'toStatusLabel' => OrderStatus::tryFrom($entry->to_status)?->label() ?? $entry->to_status,
                    'note' => $entry->note,
                    'occurredAtDiff' => $entry->occurred_at->diffForHumans(),
                ])->all(),
            ),
            'payment' => $latestPayment === null ? null : [
                'provider' => $latestPayment->provider,
                'providerReference' => $latestPayment->provider_reference,
                'checkoutUrl' => $latestPayment->checkout_url,
                'status' => $latestPayment->status,
            ],
        ];
    }
}
