<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\UpdateUserRoleAction;
use App\Domain\Admin\Models\AdminAuditLog;
use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = array_values(
            User::query()
                ->withCount('addresses')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                    'emailVerified' => $user->hasVerifiedEmail(),
                    'addressCount' => (int) $user->getAttribute('addresses_count'),
                    'joinedAtFormatted' => $user->created_at?->toFormattedDateString() ?? '—',
                ])
                ->all(),
        );

        $roleOptions = array_map(
            fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            UserRole::cases(),
        );

        $auditLogs = array_values(
            AdminAuditLog::query()
                ->with('actor:id,name,email')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(25)
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

        return Inertia::render('admin/Customers', [
            'customers' => $customers,
            'roleOptions' => $roleOptions,
            'canAssignRoles' => $request->user()->isAdmin(),
            'currentUserId' => $request->user()->id,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function updateRole(
        Request $request,
        User $customer,
        UpdateUserRoleAction $updateRole,
    ): RedirectResponse {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $newRole = UserRole::from((string) $validated['role']);

        $updateRole->execute($request->user(), $customer, $newRole);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Updated role for :email to :role.', [
                'email' => $customer->email,
                'role' => $newRole->label(),
            ]),
        ]);

        return to_route('admin.customers.index');
    }
}
