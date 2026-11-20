<?php

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Models\AccountAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validAddressPayload(array $overrides = []): array
{
    return [
        'label' => 'Home',
        'recipient_name' => 'Juan Dela Cruz',
        'phone' => '+63 917 555 0199',
        'line_1' => '123 Ayala Avenue',
        'line_2' => 'Unit 8B',
        'barangay' => 'San Lorenzo',
        'city' => 'Makati City',
        'province' => 'Metro Manila',
        'postal_code' => '1223',
        'country_code' => 'PH',
        'delivery_notes' => 'Leave at concierge',
        'is_default_shipping' => false,
        'is_default_billing' => false,
        ...$overrides,
    ];
}

test('address book page requires authentication and renders saved addresses', function () {
    $this->get(route('addresses.index'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    CustomerAddress::factory()->for($user)->defaultShipping()->defaultBilling()->create([
        'recipient_name' => 'Maria Santos',
    ]);

    $this->actingAs($user)
        ->get(route('addresses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Addresses')
            ->has('addresses', 1)
            ->where('addresses.0.recipientName', 'Maria Santos')
            ->where('addresses.0.isDefaultShipping', true)
            ->where('addresses.0.isDefaultBilling', true)
            ->where('maxAddresses', CustomerAddress::MAX_ADDRESSES_PER_USER)
        );
});

test('first saved address automatically becomes default shipping and billing and logs audit event', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('addresses.store'), validAddressPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('addresses.index'));

    $address = $user->addresses()->firstOrFail();

    expect($address->is_default_shipping)->toBeTrue()
        ->and($address->is_default_billing)->toBeTrue()
        ->and(
            AccountAuditLog::query()
                ->where('user_id', $user->id)
                ->where('event', AuditEvent::AddressCreated)
                ->exists()
        )->toBeTrue();
});

test('setting a new default address clears previous default flags for that user only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $first = CustomerAddress::factory()->for($user)->defaultShipping()->defaultBilling()->create();
    $otherAddress = CustomerAddress::factory()->for($otherUser)->defaultShipping()->defaultBilling()->create();

    $this->actingAs($user)
        ->post(route('addresses.store'), validAddressPayload([
            'label' => 'Office',
            'is_default_shipping' => true,
            'is_default_billing' => false,
        ]))
        ->assertRedirect(route('addresses.index'));

    $second = CustomerAddress::query()->where('user_id', $user->id)->whereKeyNot($first->id)->firstOrFail();

    expect($first->fresh()->is_default_shipping)->toBeFalse()
        ->and($first->fresh()->is_default_billing)->toBeTrue()
        ->and($second->is_default_shipping)->toBeTrue()
        ->and($second->is_default_billing)->toBeFalse()
        ->and($otherAddress->fresh()->is_default_shipping)->toBeTrue();

    $this->actingAs($user)
        ->patch(route('addresses.default', $second), ['type' => 'billing'])
        ->assertRedirect(route('addresses.index'));

    expect($first->fresh()->is_default_billing)->toBeFalse()
        ->and($second->fresh()->is_default_billing)->toBeTrue();
});

test('users cannot modify or delete another customer address', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $address = CustomerAddress::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->put(route('addresses.update', $address), validAddressPayload(['city' => 'Taguig']))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->patch(route('addresses.default', $address), ['type' => 'shipping'])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('addresses.destroy', $address))
        ->assertForbidden();

    expect($address->fresh())->not->toBeNull();
});

test('deleting a default address promotes a remaining address to default', function () {
    $user = User::factory()->create();
    $primary = CustomerAddress::factory()->for($user)->defaultShipping()->defaultBilling()->create();
    $backup = CustomerAddress::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('addresses.destroy', $primary))
        ->assertRedirect(route('addresses.index'));

    expect($primary->fresh())->toBeNull()
        ->and($backup->fresh()->is_default_shipping)->toBeTrue()
        ->and($backup->fresh()->is_default_billing)->toBeTrue();
});

test('customer cannot exceed the maximum saved addresses limit', function () {
    $user = User::factory()->create();
    CustomerAddress::factory()->count(CustomerAddress::MAX_ADDRESSES_PER_USER)->for($user)->create();

    $this->actingAs($user)
        ->from(route('addresses.index'))
        ->post(route('addresses.store'), validAddressPayload())
        ->assertSessionHasErrors('address');
});

test('address validation rejects malformed inputs at server boundary', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('addresses.store'), validAddressPayload([
            'label' => 'InvalidLabel',
            'phone' => 'not-a-phone!',
            'postal_code' => '@@',
            'country_code' => 'XX',
        ]))
        ->assertSessionHasErrors(['label', 'phone', 'postal_code', 'country_code']);
});
