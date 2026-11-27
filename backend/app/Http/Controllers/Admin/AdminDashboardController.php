<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Models\AdminAuditLog;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Support\Money;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $paidRevenueCents = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->get(['total_amount'])
            ->sum(fn (Order $order): int => Money::cents($order->total_amount));

        $metrics = [
            'paidRevenueFormatted' => Money::formatCents((int) $paidRevenueCents, 'PHP'),
            'totalOrders' => Order::query()->count(),
            'pendingOrders' => Order::query()
                ->whereIn('status', [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Processing])
                ->count(),
            'activeProducts' => Product::query()->where('status', ProductStatus::Active)->count(),
            'lowStockProducts' => Product::query()
                ->where('status', ProductStatus::Active)
                ->where('stock_quantity', '<=', 5)
                ->count(),
        ];

        $recentMovements = array_values(
            StockMovement::query()
                ->with(['product:id,name,sku', 'actor:id,name,email'])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(15)
                ->get()
                ->map(fn (StockMovement $movement): array => [
                    'id' => $movement->id,
                    'productName' => $movement->product->name ?? 'Deleted Product',
                    'sku' => $movement->product->sku ?? '—',
                    'type' => $movement->type->value,
                    'typeLabel' => $movement->type->label(),
                    'quantityDelta' => $movement->quantity_delta,
                    'previousQuantity' => $movement->previous_quantity,
                    'resultingQuantity' => $movement->resulting_quantity,
                    'reason' => $movement->reason,
                    'actorName' => $movement->actor->name ?? 'System',
                    'occurredAtDiff' => $movement->occurred_at->diffForHumans(),
                ])
                ->all(),
        );

        $recentAuditLogs = array_values(
            AdminAuditLog::query()
                ->with('actor:id,name,email')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(15)
                ->get()
                ->map(fn (AdminAuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'summary' => $log->summary,
                    'actorName' => $log->actor->name ?? 'System',
                    'actorRole' => $log->actor_role,
                    'ipAddress' => $log->ip_address,
                    'occurredAtDiff' => $log->occurred_at->diffForHumans(),
                ])
                ->all(),
        );

        $dailySales = $this->buildDailySalesSeries();
        $topSellingProducts = $this->buildTopSellingProducts();
        $lowStockAlerts = $this->buildLowStockAlerts();

        return Inertia::render('admin/Dashboard', [
            'metrics' => $metrics,
            'dailySales' => $dailySales,
            'topSellingProducts' => $topSellingProducts,
            'lowStockAlerts' => $lowStockAlerts,
            'recentMovements' => $recentMovements,
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }

    /**
     * @return list<array{date: string, label: string, revenueCents: int, revenueFormatted: string, orderCount: int}>
     */
    private function buildDailySalesSeries(): array
    {
        $start = CarbonImmutable::today()->subDays(6)->startOfDay();
        $orders = Order::query()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('placed_at', '>=', $start)
            ->get(['placed_at', 'total_amount']);

        $buckets = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->addDays($i);
            $key = $day->format('Y-m-d');
            $buckets[$key] = [
                'date' => $key,
                'label' => $day->format('M j'),
                'revenueCents' => 0,
                'orderCount' => 0,
            ];
        }

        foreach ($orders as $order) {
            $key = $order->placed_at->format('Y-m-d');
            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['revenueCents'] += Money::cents($order->total_amount);
            $buckets[$key]['orderCount']++;
        }

        return array_values(array_map(
            fn (array $bucket): array => [
                'date' => $bucket['date'],
                'label' => $bucket['label'],
                'revenueCents' => $bucket['revenueCents'],
                'revenueFormatted' => Money::formatCents($bucket['revenueCents'], 'PHP'),
                'orderCount' => $bucket['orderCount'],
            ],
            $buckets,
        ));
    }

    /**
     * @return list<array{productId: int|null, name: string, sku: string, unitsSold: int, revenueFormatted: string}>
     */
    private function buildTopSellingProducts(): array
    {
        $items = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled->value))
            ->get(['product_id', 'product_name', 'sku', 'quantity', 'line_total']);

        /** @var array<string, array{productId: int|null, name: string, sku: string, unitsSold: int, revenueCents: int}> $grouped */
        $grouped = [];

        foreach ($items as $item) {
            $key = $item->product_id !== null ? 'p-'.$item->product_id : 'sku-'.$item->sku;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'productId' => $item->product_id,
                    'name' => $item->product_name,
                    'sku' => $item->sku,
                    'unitsSold' => 0,
                    'revenueCents' => 0,
                ];
            }

            $grouped[$key]['unitsSold'] += $item->quantity;
            $grouped[$key]['revenueCents'] += Money::cents($item->line_total);
        }

        usort(
            $grouped,
            fn (array $a, array $b): int => $b['unitsSold'] <=> $a['unitsSold'] ?: $b['revenueCents'] <=> $a['revenueCents'],
        );

        return array_map(
            fn (array $row): array => [
                'productId' => $row['productId'],
                'name' => $row['name'],
                'sku' => $row['sku'],
                'unitsSold' => $row['unitsSold'],
                'revenueFormatted' => Money::formatCents($row['revenueCents'], 'PHP'),
            ],
            array_slice($grouped, 0, 5),
        );
    }

    /**
     * @return list<array{productId: int, name: string, sku: string, variantLabel: string, stock: int, isOutOfStock: bool}>
     */
    private function buildLowStockAlerts(): array
    {
        $alerts = [];

        $products = Product::query()
            ->where('status', ProductStatus::Active)
            ->orderBy('stock_quantity')
            ->orderBy('id')
            ->get(['id', 'name', 'sku', 'stock_quantity', 'variant_stocks']);

        foreach ($products as $product) {
            if ($product->hasVariantStocks()) {
                foreach (($product->variant_stocks ?? []) as $key => $qty) {
                    $stock = max(0, (int) $qty);
                    if ($stock > 3) {
                        continue;
                    }

                    $parts = array_filter(explode('|', (string) $key), fn (string $part): bool => trim($part) !== '');
                    $variantLabel = $parts !== [] ? implode(' / ', $parts) : 'Default Variant';

                    $alerts[] = [
                        'productId' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'variantLabel' => $variantLabel,
                        'stock' => $stock,
                        'isOutOfStock' => $stock === 0,
                    ];
                }
            } elseif ($product->stock_quantity <= 5) {
                $alerts[] = [
                    'productId' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'variantLabel' => 'Overall Stock',
                    'stock' => $product->stock_quantity,
                    'isOutOfStock' => $product->stock_quantity === 0,
                ];
            }
        }

        usort($alerts, fn (array $a, array $b): int => $a['stock'] <=> $b['stock']);

        return array_slice($alerts, 0, 10);
    }
}
