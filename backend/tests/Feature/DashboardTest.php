<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('orderStats.totalOrders', 0)
                ->where('orderStats.activeOrders', 0)
                ->where('cartOverview.itemCount', 0)
                ->where('addressOverview.savedCount', 0)
                ->has('recentOrders', 0)
                ->where('activeOrderTracker', null)
                ->has('wishlistItems', 0)
                ->has('buyAgainItems', 0)
                ->where('accountSecurity.emailVerified', true)
            );
    }

    public function test_wishlist_toggle_and_active_order_tracker_and_buy_again_render_on_dashboard(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->defaultShipping()->create();
        $wishlistedProduct = Product::factory()->create(['name' => 'Wishlist DAC']);
        $purchasedProduct = Product::factory()->create(['name' => 'Purchased Keyboard', 'stock_quantity' => 5]);
        $draftProduct = Product::factory()->draft()->create();

        // Draft product cannot be added to wishlist
        $this->actingAs($user)
            ->post(route('wishlist.toggle'), ['product_id' => $draftProduct->id])
            ->assertSessionHasErrors('product_id');

        // Toggle wishlist ON for published product
        $this->actingAs($user)
            ->post(route('wishlist.toggle'), ['product_id' => $wishlistedProduct->id])
            ->assertSessionHasNoErrors();

        // Place an order for purchasedProduct so activeOrderTracker and buyAgainItems populate
        $this->actingAs($user)->post(route('cart.items.store'), [
            'product_id' => $purchasedProduct->id,
            'quantity' => 1,
        ]);
        $this->actingAs($user)->post(route('checkout.store'), [
            'payment_method' => 'paymongo_checkout',
            'address_id' => $address->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('orderStats.totalOrders', 1)
                ->where('orderStats.activeOrders', 1)
                ->has('wishlistItems', 1)
                ->where('wishlistItems.0.name', 'Wishlist DAC')
                ->has('buyAgainItems', 1)
                ->where('buyAgainItems.0.name', 'Purchased Keyboard')
                ->where('activeOrderTracker.currentStepIndex', 0)
                ->has('activeOrderTracker.steps', 5)
            );

        // Toggle wishlist OFF removes the item
        $this->actingAs($user)
            ->post(route('wishlist.toggle'), ['product_id' => $wishlistedProduct->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('wishlistItems', 0));
    }
}
