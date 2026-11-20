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
use App\Http\Controllers\Controller;
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

        return Inertia::render('admin/Dashboard', [
            'metrics' => $metrics,
            'recentMovements' => $recentMovements,
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }
}
