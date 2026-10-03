<?php

declare(strict_types=1);

use Modules\Booking\Exceptions\InvalidBookingTransitionException;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\BookingStateMachine;

it('allows the full happy-path lifecycle', function (): void {
    expect(BookingStateMachine::canTransition(Booking::STATUS_HOLD, Booking::STATUS_CONFIRMED))->toBeTrue()
        ->and(BookingStateMachine::canTransition(Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE))->toBeTrue()
        ->and(BookingStateMachine::canTransition(Booking::STATUS_ACTIVE, Booking::STATUS_COMPLETED))->toBeTrue();
});

it('allows the pending-payment path', function (): void {
    expect(BookingStateMachine::canTransition(Booking::STATUS_HOLD, Booking::STATUS_PENDING_PAYMENT))->toBeTrue()
        ->and(BookingStateMachine::canTransition(Booking::STATUS_PENDING_PAYMENT, Booking::STATUS_CONFIRMED))->toBeTrue();
});

it('allows cancellation from hold, pending_payment, confirmed, and active', function (): void {
    foreach ([Booking::STATUS_HOLD, Booking::STATUS_PENDING_PAYMENT, Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE] as $status) {
        expect(BookingStateMachine::canTransition($status, Booking::STATUS_CANCELLED))->toBeTrue();
    }
});

it('allows no-show only from confirmed', function (): void {
    expect(BookingStateMachine::canTransition(Booking::STATUS_CONFIRMED, Booking::STATUS_NO_SHOW))->toBeTrue()
        ->and(BookingStateMachine::canTransition(Booking::STATUS_ACTIVE, Booking::STATUS_NO_SHOW))->toBeFalse()
        ->and(BookingStateMachine::canTransition(Booking::STATUS_HOLD, Booking::STATUS_NO_SHOW))->toBeFalse();
});

it('rejects skipping straight from hold to active', function (): void {
    expect(BookingStateMachine::canTransition(Booking::STATUS_HOLD, Booking::STATUS_ACTIVE))->toBeFalse();
});

it('rejects any transition out of a terminal state', function (): void {
    foreach ([Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW, Booking::STATUS_EXPIRED] as $terminal) {
        expect(BookingStateMachine::canTransition($terminal, Booking::STATUS_CONFIRMED))->toBeFalse();
    }
});

it('throws a typed exception on an invalid transition', function (): void {
    BookingStateMachine::assertCanTransition(Booking::STATUS_COMPLETED, Booking::STATUS_ACTIVE);
})->throws(InvalidBookingTransitionException::class);

it('does not throw on a valid transition', function (): void {
    BookingStateMachine::assertCanTransition(Booking::STATUS_HOLD, Booking::STATUS_CANCELLED);
})->throwsNoExceptions();
