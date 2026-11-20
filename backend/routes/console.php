<?php

use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('xhopy:user-role {email} {role=admin}', function (string $email, string $role) {
    $targetRole = UserRole::tryFrom(strtolower(trim($role)));

    if ($targetRole === null) {
        $this->error('Invalid role. Allowed roles: customer, staff, admin.');

        return 1;
    }

    $user = User::query()->where('email', strtolower(trim($email)))->first();

    if ($user === null) {
        $this->error("No user found with email [{$email}].");

        return 1;
    }

    $user->assignRole($targetRole);

    $this->info("Assigned role [{$targetRole->value}] to {$user->name} ({$user->email}).");

    return 0;
})->purpose('Assign a platform role (customer, staff, admin) to an existing user account');
