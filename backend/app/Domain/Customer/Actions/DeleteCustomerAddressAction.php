<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Services\AccountAuditRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteCustomerAddressAction
{
    public function __construct(private readonly AccountAuditRecorder $audit) {}

    public function execute(User $user, CustomerAddress $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            $wasDefaultShipping = $address->is_default_shipping;
            $wasDefaultBilling = $address->is_default_billing;
            $deletedId = $address->id;
            $deletedLabel = $address->label;

            $address->delete();

            if ($wasDefaultShipping || $wasDefaultBilling) {
                $fallback = CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->first();

                if ($fallback !== null) {
                    if ($wasDefaultShipping) {
                        $fallback->is_default_shipping = true;
                    }
                    if ($wasDefaultBilling) {
                        $fallback->is_default_billing = true;
                    }
                    $fallback->save();
                }
            }

            $this->audit->record(
                AuditEvent::AddressDeleted,
                $user,
                [
                    'address_id' => $deletedId,
                    'label' => $deletedLabel,
                ],
            );
        });
    }
}
