<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Support\ProductPresenter;
use App\Domain\Customer\Services\WishlistService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Support\OrderPresenter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CartService $cartService,
        OrderPresenter $orderPresenter,
        ProductPresenter $productPresenter,
        WishlistService $wishlistService,
    ): Response {
        $user = $request->user();

        $totalOrders = Order::query()
            ->where('user_id', $user->id)
            ->count();

        $activeOrders = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                OrderStatus::PendingPayment,
                OrderStatus::Paid,
                OrderStatus::Processing,
                OrderStatus::Shipped,
            ])
            ->count();

        $recentOrdersModels = Order::query()
            ->where('user_id', $user->id)
            ->with('items')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentOrders = array_values(
            $recentOrdersModels
                ->map(fn (Order $order): array => $orderPresenter->summary($order))
                ->all(),
        );

        $trackedOrder = $recentOrdersModels->first(
            fn (Order $order): bool => $order->status !== OrderStatus::Cancelled,
        );

        $addresses = $user->addresses()->get();
        $defaultShipping = $addresses->firstWhere('is_default_shipping', true) ?? $addresses->first();

        $cart = $cartService->present($request);
        $wishlistItems = $wishlistService->presentForUser($user);
        $buyAgainItems = $this->resolveBuyAgainItems($user, $productPresenter);

        return Inertia::render('Dashboard', [
            'orderStats' => [
                'totalOrders' => $totalOrders,
                'activeOrders' => $activeOrders,
            ],
            'cartOverview' => [
                'itemCount' => $cart['itemCount'],
                'subtotalFormatted' => $cart['subtotalFormatted'],
            ],
            'addressOverview' => [
                'savedCount' => $addresses->count(),
                'defaultShipping' => $defaultShipping?->toFrontendArray(),
            ],
            'recentOrders' => $recentOrders,
            'activeOrderTracker' => $trackedOrder === null ? null : $this->buildOrderTracker($trackedOrder, $orderPresenter),
            'wishlistItems' => $wishlistItems,
            'buyAgainItems' => $buyAgainItems,
            'accountSecurity' => [
                'emailVerified' => $user->hasVerifiedEmail(),
                'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
                'role' => $user->role->value,
                'roleLabel' => $user->role->label(),
                'canAccessAdmin' => $user->isStaffOrAdmin(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderTracker(Order $order, OrderPresenter $orderPresenter): array
    {
        $steps = [
            ['key' => OrderStatus::PendingPayment->value, 'label' => 'Order Placed'],
            ['key' => OrderStatus::Paid->value, 'label' => 'Payment Verified'],
            ['key' => OrderStatus::Processing->value, 'label' => 'Packing'],
            ['key' => OrderStatus::Shipped->value, 'label' => 'In Transit'],
            ['key' => OrderStatus::Delivered->value, 'label' => 'Delivered'],
        ];

        $currentStepIndex = match ($order->status) {
            OrderStatus::PendingPayment => 0,
            OrderStatus::Paid => 1,
            OrderStatus::Processing => 2,
            OrderStatus::Shipped => 3,
            OrderStatus::Delivered => 4,
            OrderStatus::Cancelled => -1,
        };

        return [
            ...$orderPresenter->summary($order),
            'currentStepIndex' => $currentStepIndex,
            'steps' => $steps,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveBuyAgainItems(User $user, ProductPresenter $productPresenter): array
    {
        $purchasedProductIds = OrderItem::query()
            ->whereIn(
                'order_id',
                Order::query()
                    ->where('user_id', $user->id)
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->select('id'),
            )
            ->whereNotNull('product_id')
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('product_id')
            ->unique()
            ->take(4)
            ->values()
            ->all();

        if ($purchasedProductIds === []) {
            return [];
        }

        return array_values(
            Product::query()
                ->published()
                ->whereIn('products.id', $purchasedProductIds)
                ->with(['category:id,name,slug', 'primaryImage'])
                ->limit(4)
                ->get()
                ->map(fn (Product $product): array => $productPresenter->summary($product))
                ->all(),
        );
    }
}
