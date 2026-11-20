<?php

namespace App\Domain\Customer\Policies;

use App\Domain\Customer\Models\CustomerAddress;
use App\Models\User;

final class CustomerAddressPolicy
{
    public function update(User $user, CustomerAddress $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function delete(User $user, CustomerAddress $address): bool
    {
        return $address->user_id === $user->id;
    }
}
