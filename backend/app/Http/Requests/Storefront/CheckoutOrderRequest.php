<?php

namespace App\Http\Requests\Storefront;

use App\Domain\Checkout\Actions\PlaceOrderAction;
use App\Http\Requests\Settings\CustomerAddressRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $usingSavedAddress = $this->filled('address_id');

        return [
            'idempotency_key' => ['nullable', 'string', 'max:64', 'alpha_dash'],
            'payment_method' => ['required', 'string', Rule::in(PlaceOrderAction::ALLOWED_PAYMENT_METHODS)],
            'address_id' => ['nullable', 'integer', 'min:1'],
            'recipient_name' => [$usingSavedAddress ? 'nullable' : 'required', 'string', 'max:120'],
            'phone' => [$usingSavedAddress ? 'nullable' : 'required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{7,32}$/'],
            'line_1' => [$usingSavedAddress ? 'nullable' : 'required', 'string', 'max:180'],
            'line_2' => ['nullable', 'string', 'max:180'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'city' => [$usingSavedAddress ? 'nullable' : 'required', 'string', 'max:120'],
            'province' => [$usingSavedAddress ? 'nullable' : 'required', 'string', 'max:120'],
            'postal_code' => [$usingSavedAddress ? 'nullable' : 'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\s]{3,20}$/'],
            'country_code' => ['nullable', 'string', Rule::in(CustomerAddressRequest::ALLOWED_COUNTRIES)],
            'shipping_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
