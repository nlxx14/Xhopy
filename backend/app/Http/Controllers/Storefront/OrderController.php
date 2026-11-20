<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateTransitionService;
use App\Domain\Orders\Support\OrderPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request, OrderPresenter $presenter): Response
    {
        $orders = array_values(
            Order::query()
                ->where('user_id', $request->user()->id)
                ->with('items')
                ->orderByDesc('placed_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Order $order): array => $presenter->summary($order))
                ->all(),
        );

        return Inertia::render('orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(string $orderNumber, OrderPresenter $presenter): Response
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->with(['items', 'statusHistories', 'latestPayment'])
            ->firstOrFail();

        Gate::authorize('view', $order);

        return Inertia::render('orders/Show', [
            'order' => $presenter->detail($order),
        ]);
    }

    public function cancel(
        Request $request,
        string $orderNumber,
        OrderStateTransitionService $transitions,
    ): RedirectResponse {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        Gate::authorize('cancel', $order);

        $transitions->transition(
            $order,
            OrderStatus::Cancelled,
            $request->user(),
            'Cancelled by customer.',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order cancelled and reserved stock restored.')]);

        return back();
    }
}
