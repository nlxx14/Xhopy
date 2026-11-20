<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Services\AccountAuditRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveCustomerAddressAction
{
    public function __construct(private readonly AccountAuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $user, array $attributes, ?CustomerAddress $existing = null): CustomerAddress
    {
        return DB::transaction(function () use ($user, $attributes, $existing): CustomerAddress {
            $isCreating = $existing === null;

            if ($isCreating) {
                $count = CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->count();

                if ($count >= CustomerAddress::MAX_ADDRESSES_PER_USER) {
                    throw ValidationException::withMessages([
                        'address' => sprintf('You may save up to %d addresses.', CustomerAddress::MAX_ADDRESSES_PER_USER),
                    ]);
                }

                $isFirstAddress = $count === 0;
            } else {
                $isFirstAddress = false;
            }

            $defaultShipping = $isFirstAddress || (bool) ($attributes['is_default_shipping'] ?? false);
            $defaultBilling = $isFirstAddress || (bool) ($attributes['is_default_billing'] ?? false);

            if ($defaultShipping) {
                CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->when($existing !== null, fn ($q) => $q->whereKeyNot($existing->id))
                    ->update(['is_default_shipping' => false]);
            }

            if ($defaultBilling) {
                CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->when($existing !== null, fn ($q) => $q->whereKeyNot($existing->id))
                    ->update(['is_default_billing' => false]);
            }

            $payload = [
                'user_id' => $user->id,
                'label' => (string) $attributes['label'],
                'recipient_name' => (string) $attributes['recipient_name'],
                'phone' => (string) $attributes['phone'],
                'line_1' => (string) $attributes['line_1'],
                'line_2' => isset($attributes['line_2']) && $attributes['line_2'] !== '' ? (string) $attributes['line_2'] : null,
                'barangay' => isset($attributes['barangay']) && $attributes['barangay'] !== '' ? (string) $attributes['barangay'] : null,
                'city' => (string) $attributes['city'],
                'province' => (string) $attributes['province'],
                'postal_code' => (string) $attributes['postal_code'],
                'country_code' => strtoupper((string) ($attributes['country_code'] ?? 'PH')),
                'delivery_notes' => isset($attributes['delivery_notes']) && $attributes['delivery_notes'] !== '' ? (string) $attributes['delivery_notes'] : null,
                'is_default_shipping' => $defaultShipping,
                'is_default_billing' => $defaultBilling,
            ];

            if ($existing !== null) {
                $existing->fill($payload)->save();
                $address = $existing;
            } else {
                $address = CustomerAddress::query()->create($payload);
            }

            $this->audit->record(
                $isCreating ? AuditEvent::AddressCreated : AuditEvent::AddressUpdated,
                $user,
                [
                    'address_id' => $address->id,
                    'label' => $address->label,
                    'city' => $address->city,
                ],
            );

            return $address;
        });
    }
}
