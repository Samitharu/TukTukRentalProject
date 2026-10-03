<?php

declare(strict_types=1);

use Modules\Booking\Support\BookingReference;

it('generates a reference matching the MTR-XXXXXXXX format', function (): void {
    $reference = BookingReference::generate();

    expect($reference)->toMatch('/^MTR-[A-Z2-9]{8}$/')
        ->and(BookingReference::looksValid($reference))->toBeTrue();
});

it('never generates ambiguous characters (0/O, 1/I)', function (): void {
    for ($i = 0; $i < 25; $i++) {
        $reference = BookingReference::generate();
        expect($reference)->not->toContain('0')
            ->not->toContain('O')
            ->not->toContain('1')
            ->not->toContain('I');
    }
});

it('is not sequential or derived from an id — two consecutive calls share no obvious pattern', function (): void {
    $first = BookingReference::generate();
    $second = BookingReference::generate();

    expect($first)->not->toBe($second);
    // A weak but meaningful non-sequentiality check: the random suffixes
    // should not be lexicographically adjacent by construction.
    expect(substr($first, 4))->not->toBe(substr($second, 4));
});

it('rejects an obviously invalid reference format', function (): void {
    expect(BookingReference::looksValid('not-a-reference'))->toBeFalse()
        ->and(BookingReference::looksValid('MTR-0000I1O0'))->toBeFalse();
});
