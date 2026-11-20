<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Customer\Actions\DeleteCustomerAddressAction;
use App\Domain\Customer\Actions\SaveCustomerAddressAction;
use App\Domain\Customer\Actions\SetDefaultCustomerAddressAction;
use App\Domain\Customer\Models\CustomerAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CustomerAddressRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    /**
     * Render the customer address book page.
     */
    public function index(Request $request): Response
    {
        $addresses = array_values(
            $request->user()
                ->addresses()
                ->get()
                ->map(fn (CustomerAddress $address): array => $address->toFrontendArray())
                ->all(),
        );

        return Inertia::render('settings/Addresses', [
            'addresses' => $addresses,
            'maxAddresses' => CustomerAddress::MAX_ADDRESSES_PER_USER,
            'allowedLabels' => CustomerAddressRequest::ALLOWED_LABELS,
        ]);
    }

    /**
     * Save a new customer address.
     */
    public function store(
        CustomerAddressRequest $request,
        SaveCustomerAddressAction $saveAddress,
    ): RedirectResponse {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $saveAddress->execute($request->user(), $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address saved.')]);

        return to_route('addresses.index');
    }

    /**
     * Update an existing customer address owned by the user.
     */
    public function update(
        CustomerAddressRequest $request,
        CustomerAddress $address,
        SaveCustomerAddressAction $saveAddress,
    ): RedirectResponse {
        Gate::authorize('update', $address);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $saveAddress->execute($request->user(), $validated, $address);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return to_route('addresses.index');
    }

    /**
     * Mark an address owned by the user as default shipping, billing, or both.
     */
    public function setDefault(
        Request $request,
        CustomerAddress $address,
        SetDefaultCustomerAddressAction $setDefault,
    ): RedirectResponse {
        Gate::authorize('update', $address);

        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['shipping', 'billing', 'both'])],
        ]);

        /** @var 'shipping'|'billing'|'both' $type */
        $type = $validated['type'] ?? 'both';

        $setDefault->execute($request->user(), $address, $type);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default address updated.')]);

        return to_route('addresses.index');
    }

    /**
     * Delete an address owned by the user.
     */
    public function destroy(
        Request $request,
        CustomerAddress $address,
        DeleteCustomerAddressAction $deleteAddress,
    ): RedirectResponse {
        Gate::authorize('delete', $address);

        $deleteAddress->execute($request->user(), $address);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address removed.')]);

        return to_route('addresses.index');
    }
}
