<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Customer\Services\WishlistService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function toggle(
        Request $request,
        WishlistService $wishlist,
    ): RedirectResponse {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
        ]);

        $added = $wishlist->toggle($request->user(), (int) $validated['product_id']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $added
                ? __('Saved to your wishlist.')
                : __('Removed from your wishlist.'),
        ]);

        return back();
    }

    public function destroy(
        Request $request,
        int $productId,
        WishlistService $wishlist,
    ): RedirectResponse {
        $wishlist->remove($request->user(), $productId);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removed from your wishlist.'),
        ]);

        return back();
    }
}
