<?php

declare(strict_types=1);

use Modules\Package\Services\PackagePricingTierValidator;

beforeEach(function (): void {
    $this->validator = new PackagePricingTierValidator();
});

it('accepts a contiguous set of tiers with an open-ended final tier', function (): void {
    $errors = $this->validator->validate([
        ['min_days' => 1, 'max_days' => 3],
        ['min_days' => 4, 'max_days' => 7],
        ['min_days' => 8, 'max_days' => null],
    ]);

    expect($errors)->toBeEmpty();
});

it('accepts a single closed tier', function (): void {
    expect($this->validator->validate([['min_days' => 1, 'max_days' => 5]]))->toBeEmpty();
});

it('rejects an empty tier list', function (): void {
    expect($this->validator->validate([]))->not->toBeEmpty();
});

it('detects a gap between tiers', function (): void {
    $errors = $this->validator->validate([
        ['min_days' => 1, 'max_days' => 3],
        ['min_days' => 6, 'max_days' => 10],
    ]);

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('gap');
});

it('detects an overlap between tiers', function (): void {
    $errors = $this->validator->validate([
        ['min_days' => 1, 'max_days' => 5],
        ['min_days' => 4, 'max_days' => 10],
    ]);

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('overlap');
});

it('rejects more than one open-ended tier', function (): void {
    $errors = $this->validator->validate([
        ['min_days' => 1, 'max_days' => null],
        ['min_days' => 5, 'max_days' => null],
    ]);

    expect($errors)->not->toBeEmpty();
});

it('rejects an open-ended tier that is not last', function (): void {
    $errors = $this->validator->validate([
        ['min_days' => 1, 'max_days' => null],
        ['min_days' => 5, 'max_days' => 10],
    ]);

    expect($errors)->not->toBeEmpty();
});

it('is order-independent — unsorted input is still validated correctly', function (): void {
    $errors = $this->validator->validate([
        ['min_days' => 8, 'max_days' => null],
        ['min_days' => 1, 'max_days' => 3],
        ['min_days' => 4, 'max_days' => 7],
    ]);

    expect($errors)->toBeEmpty();
});
