<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateTransitionService;
use App\Domain\Orders\Support\OrderPresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AdminOrderController extends Controller
{
    public function index(OrderPresenter $presenter): Response
    {
        $orders = array_values(
            Order::query()
                ->with(['user:id,name,email', 'items'])
                ->orderByDesc('placed_at')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(function (Order $order) use ($presenter): array {
                    $allowedNext = array_values(array_map(
                        fn (OrderStatus $candidate): array => [
                            'value' => $candidate->value,
                            'label' => $candidate->label(),
                        ],
                        array_filter(
                            OrderStatus::cases(),
                            fn (OrderStatus $candidate): bool => $order->status->canTransitionTo($candidate),
                        ),
                    ));

                    return [
                        ...$presenter->summary($order),
                        'customerName' => $order->user->name,
                        'customerEmail' => $order->user->email,
                        'shippingCity' => $order->shipping_city,
                        'shippingProvince' => $order->shipping_province,
                        'allowedTransitions' => $allowedNext,
                    ];
                })
                ->all(),
        );

        return Inertia::render('admin/Orders', [
            'orders' => $orders,
        ]);
    }

    public function updateStatus(
        Request $request,
        Order $order,
        OrderStateTransitionService $transitions,
        AdminAuditRecorder $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $targetStatus = OrderStatus::from((string) $validated['status']);
        $previousStatus = $order->status;
        $note = isset($validated['note']) && trim((string) $validated['note']) !== ''
            ? trim((string) $validated['note'])
            : sprintf('Updated by %s (%s).', $request->user()->name, $request->user()->role->value);

        try {
            $updated = $transitions->transition($order, $targetStatus, $request->user(), $note);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'status' => $exception->getMessage(),
            ]);
        }

        if ($targetStatus === OrderStatus::Paid && $updated->payment_status !== PaymentStatus::Paid) {
            $updated->payment_status = PaymentStatus::Paid;
            $updated->paid_at = $updated->paid_at ?? now();
            $updated->save();
        }

        $audit->record(
            $request->user(),
            'order.status_transitioned',
            sprintf(
                'Transitioned order %s from "%s" to "%s".',
                $updated->order_number,
                $previousStatus->value,
                $targetStatus->value,
            ),
            'Order',
            $updated->id,
            [
                'order_number' => $updated->order_number,
                'from' => $previousStatus->value,
                'to' => $targetStatus->value,
                'note' => $note,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order :number moved to :status.', [
                'number' => $updated->order_number,
                'status' => $targetStatus->label(),
            ]),
        ]);

        return to_route('admin.orders.index');
    }
}
