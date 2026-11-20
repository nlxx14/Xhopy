<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Services\AccountAuditRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SetDefaultCustomerAddressAction
{
    public function __construct(private readonly AccountAuditRecorder $audit) {}

    /**
     * @param  'shipping'|'billing'|'both'  $type
     */
    public function execute(User $user, CustomerAddress $address, string $type = 'both'): CustomerAddress
    {
        return DB::transaction(function () use ($user, $address, $type): CustomerAddress {
            if ($type === 'shipping' || $type === 'both') {
                CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->whereKeyNot($address->id)
                    ->update(['is_default_shipping' => false]);

                $address->is_default_shipping = true;
            }

            if ($type === 'billing' || $type === 'both') {
                CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->whereKeyNot($address->id)
                    ->update(['is_default_billing' => false]);

                $address->is_default_billing = true;
            }

            $address->save();

            $this->audit->record(
                AuditEvent::AddressUpdated,
                $user,
                [
                    'address_id' => $address->id,
                    'default_type' => $type,
                ],
            );

            return $address;
        });
    }
}
