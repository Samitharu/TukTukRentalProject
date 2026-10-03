<?php

declare(strict_types=1);

use Modules\Package\Services\PackageSeasonValidator;

beforeEach(function (): void {
    $this->validator = new PackageSeasonValidator();
});

const PSV_ALL_DAYS = 127; // bits 0-6 set

it('accepts non-overlapping date ranges', function (): void {
    $errors = $this->validator->validate([
        ['id' => 1, 'name' => 'Peak', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-10', 'weekday_mask' => PSV_ALL_DAYS],
        ['id' => 2, 'name' => 'Off-peak', 'starts_on' => '2026-06-11', 'ends_on' => '2026-06-20', 'weekday_mask' => PSV_ALL_DAYS],
    ]);

    expect($errors)->toBeEmpty();
});

it('rejects two seasons with the same weekday mask over an overlapping range', function (): void {
    $errors = $this->validator->validate([
        ['id' => 1, 'name' => 'Peak', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-15', 'weekday_mask' => PSV_ALL_DAYS],
        ['id' => 2, 'name' => 'Festival', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-20', 'weekday_mask' => PSV_ALL_DAYS],
    ]);

    expect($errors)->not->toBeEmpty();
});

it('allows overlapping date ranges when the weekday masks do not share a day', function (): void {
    $weekdays = 0b0011111; // Mon-Fri (bits 0-4)
    $weekend = 0b1100000; // Sat-Sun (bits 5-6)

    $errors = $this->validator->validate([
        ['id' => 1, 'name' => 'Weekday rate', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-30', 'weekday_mask' => $weekdays],
        ['id' => 2, 'name' => 'Weekend rate', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-30', 'weekday_mask' => $weekend],
    ]);

    expect($errors)->toBeEmpty();
});

it('ignores comparing a season against itself when the same id repeats', function (): void {
    $errors = $this->validator->validate([
        ['id' => 1, 'name' => 'Peak', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-10', 'weekday_mask' => PSV_ALL_DAYS],
        ['id' => 1, 'name' => 'Peak', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-10', 'weekday_mask' => PSV_ALL_DAYS],
    ]);

    expect($errors)->toBeEmpty();
});
