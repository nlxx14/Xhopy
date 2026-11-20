<?php

use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('home shows real featured products first and limits the collection', function () {
    Product::factory()->count(9)->create();
    Product::factory()->featured(1)->create(['name' => 'Top Pick']);
    Product::factory()->draft()->create(['name' => 'Draft Pick']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('isDemoPreview', false)
            ->has('topProducts', 8)
            ->where('topProducts.0.name', 'Top Pick')
        );
});

test('home lists real categories and filters real products by category and search', function () {
    $audio = Category::factory()->create(['name' => 'Audio', 'slug' => 'audio']);
    Product::factory()->for($audio)->create(['name' => 'Headphones']);
    Product::factory()->for($audio)->create(['name' => 'Speaker']);
    Product::factory()->create(['name' => 'Other']);

    $this->get(route('home', ['category' => 'audio', 'q' => 'speak']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 2)
            ->has('topProducts', 1)
            ->where('topProducts.0.name', 'Speaker')
            ->where('selectedCategory', 'audio')
        );
});

test('daily interest is hidden until a featured banner is live', function () {
    CampaignContent::factory()->create(); // tile only, no banner

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('dailyInterest', null));
});

test('daily interest renders the live banner and tiles in order', function () {
    CampaignContent::factory()->featuredBanner()->create(['title' => 'Banner', 'href' => '/products']);
    CampaignContent::factory()->create(['title' => 'Second', 'sort_order' => 2, 'href' => '/products?sort=newest']);
    CampaignContent::factory()->create(['title' => 'First', 'sort_order' => 1, 'href' => 'https://example.com/guide']);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dailyInterest.featured.title', 'Banner')
            ->has('dailyInterest.tiles', 2)
            ->where('dailyInterest.tiles.0.title', 'First')
            ->where('dailyInterest.tiles.1.title', 'Second')
        );
});

test('daily interest excludes inactive, scheduled, expired and unsafe-link content', function () {
    CampaignContent::factory()->featuredBanner()->create(['title' => 'Banner']);
    CampaignContent::factory()->inactive()->create(['title' => 'Inactive']);
    CampaignContent::factory()->create(['title' => 'Future', 'starts_at' => now()->addDay()]);
    CampaignContent::factory()->create(['title' => 'Expired', 'ends_at' => now()->subMinute()]);
    CampaignContent::factory()->create(['title' => 'Script', 'href' => 'javascript:alert(1)']);
    CampaignContent::factory()->create(['title' => 'Protocol relative', 'href' => '//evil.example']);
    CampaignContent::factory()->create(['title' => 'Insecure', 'href' => 'http://example.com']);
    CampaignContent::factory()->create(['title' => 'Windowed', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dailyInterest.tiles', 1)
            ->where('dailyInterest.tiles.0.title', 'Windowed')
        );
});

test('daily interest placements are stored as enum values', function () {
    $banner = CampaignContent::factory()->featuredBanner()->create();

    expect($banner->fresh()->placement)->toBe(CampaignPlacement::DailyInterestFeatured);
});

test('home with a populated database never mixes in demo records', function () {
    Product::factory()->create(['name' => 'Real Product']);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('topProducts', 1)
            ->where('topProducts.0.name', 'Real Product')
            ->where('announcement', null)
        );
});
