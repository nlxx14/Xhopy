<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Services\AccountAuditRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateUserRoleAction
{
    public function __construct(
        private readonly AdminAuditRecorder $adminAudit,
        private readonly AccountAuditRecorder $accountAudit,
    ) {}

    public function execute(User $actor, User $target, UserRole $newRole): User
    {
        if ($actor->id === $target->id && $newRole !== UserRole::Admin) {
            throw ValidationException::withMessages([
                'role' => __('You cannot remove your own administrator role.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $target, $newRole): User {
            $previousRole = $target->role;

            if ($previousRole === $newRole) {
                return $target;
            }

            $target->assignRole($newRole);

            $this->accountAudit->record(
                AuditEvent::RoleChanged,
                $target,
                [
                    'from' => $previousRole->value,
                    'to' => $newRole->value,
                ],
                $actor,
            );

            $this->adminAudit->record(
                $actor,
                'customer.role_updated',
                sprintf('Changed role for %s from "%s" to "%s".', $target->email, $previousRole->value, $newRole->value),
                'User',
                $target->id,
                [
                    'from' => $previousRole->value,
                    'to' => $newRole->value,
                ],
            );

            return $target;
        });
    }
}
