<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerAddressRequest extends FormRequest
{
    public const ALLOWED_LABELS = ['Home', 'Office', 'Other'];

    public const ALLOWED_COUNTRIES = ['PH'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', Rule::in(self::ALLOWED_LABELS)],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{7,32}$/'],
            'line_1' => ['required', 'string', 'max:180'],
            'line_2' => ['nullable', 'string', 'max:180'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\s]{3,20}$/'],
            'country_code' => ['nullable', 'string', Rule::in(self::ALLOWED_COUNTRIES)],
            'delivery_notes' => ['nullable', 'string', 'max:255'],
            'is_default_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
        ];
    }
}
