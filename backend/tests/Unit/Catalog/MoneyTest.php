<?php

use App\Domain\Catalog\Support\Money;
use App\Domain\Catalog\Support\ProductPresenter;

test('money converts decimal strings to exact integer cents', function (string $amount, int $cents) {
    expect(Money::cents($amount))->toBe($cents);
})->with([
    ['0', 0],
    ['0.5', 50],
    ['0.05', 5],
    ['6490.00', 649000],
    ['19.99', 1999],
    ['9999999.99', 999999999],
    ['-12.30', -1230],
]);

test('money rejects invalid amounts', function (string $amount) {
    Money::cents($amount);
})->with(['abc', '1.234', '1,000.00', '', '1e3'])->throws(InvalidArgumentException::class);

test('money formats minor units with currency symbol and grouping', function () {
    expect(Money::formatCents(649000))->toBe('₱6,490.00')
        ->and(Money::formatCents(5))->toBe('₱0.05')
        ->and(Money::formatCents(123456789))->toBe('₱1,234,567.89')
        ->and(Money::formatCents(-1230))->toBe('-₱12.30')
        ->and(Money::formatCents(1999, 'USD'))->toBe('$19.99')
        ->and(Money::formatCents(1999, 'EUR'))->toBe('EUR 19.99')
        ->and(Money::format('6490.00'))->toBe('₱6,490.00');
});

test('campaign hrefs must be same-site paths or https urls', function (string $href, bool $safe) {
    expect(ProductPresenter::isSafeHref($href))->toBe($safe);
})->with([
    ['/products', true],
    ['/products?category=audio#top', true],
    ['https://example.com/guide', true],
    ['//evil.example', false],
    ['/\\evil.example', false],
    ['http://example.com', false],
    ['javascript:alert(1)', false],
    ['data:text/html,hi', false],
    ['products', false],
    ['', false],
]);
