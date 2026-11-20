<?php

namespace Database\Factories;

use App\Domain\Customer\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Home',
            'recipient_name' => fake()->name(),
            'phone' => '+63 917 555 0199',
            'line_1' => fake()->streetAddress(),
            'line_2' => null,
            'barangay' => 'San Lorenzo',
            'city' => 'Makati City',
            'province' => 'Metro Manila',
            'postal_code' => '1223',
            'country_code' => 'PH',
            'delivery_notes' => null,
            'is_default_shipping' => false,
            'is_default_billing' => false,
        ];
    }

    public function defaultShipping(): static
    {
        return $this->state(fn (): array => ['is_default_shipping' => true]);
    }

    public function defaultBilling(): static
    {
        return $this->state(fn (): array => ['is_default_billing' => true]);
    }
}
