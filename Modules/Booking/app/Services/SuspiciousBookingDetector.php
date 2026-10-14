<?php

declare(strict_types=1);

namespace Modules\Booking\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Booking\Models\Booking;

/**
 * Spots one person holding many upcoming bookings — the pattern of a
 * competitor blocking the fleet with fake, free bookings. It only *flags*
 * for staff: a real traveller can book several legs of a trip, so nothing
 * here cancels or rejects a booking on its own.
 *
 * Bookings are "related" when they share any signal below, and count only
 * while open (not yet picked up) and not yet over.
 */
final class SuspiciousBookingDetector
{
    /** Booking column => what staff see it as. */
    public const array SIGNALS = [
        'customer_id' => 'Same customer (email)',
        'ip_address' => 'Same IP address',
        'phone_key' => 'Same phone number',
        'passport_key' => 'Same passport number',
    ];

    private const int RELATED_LIMIT = 200;

    /**
     * Which of these bookings are flagged, and by which signals.
     *
     * One grouped count per signal for the whole page, rather than a
     * query per booking.
     *
     * @param  iterable<Booking>  $bookings
     * @return array<int, string[]> booking id => signal labels
     */
    public function flagsFor(iterable $bookings): array
    {
        $candidates = collect($bookings)->filter(fn (Booking $b) => $this->isOpenAndUpcoming($b));

        if ($candidates->isEmpty()) {
            return [];
        }

        $threshold = $this->threshold();
        $flags = [];

        foreach (self::SIGNALS as $column => $label) {
            $values = $candidates->pluck($column)->filter()->unique()->values();

            if ($values->isEmpty()) {
                continue;
            }

            $counts = $this->openUpcoming()
                ->whereIn($column, $values)
                ->groupBy($column)
                ->selectRaw("{$column} as signal_value, count(*) as aggregate")
                ->pluck('aggregate', 'signal_value');

            foreach ($candidates as $booking) {
                if (filled($booking->{$column}) && (int) ($counts[(string) $booking->{$column}] ?? 0) >= $threshold) {
                    $flags[$booking->id][] = $label;
                }
            }
        }

        return $flags;
    }

    /** Narrows a bookings query to flagged bookings only. */
    public function scopeToSuspicious(Builder $query): Builder
    {
        $threshold = $this->threshold();

        return $query
            ->whereIn('status', Booking::OPEN_STATUSES)
            ->where('end_at', '>=', today())
            ->where(function (Builder $q) use ($threshold): void {
                foreach (array_keys(self::SIGNALS) as $column) {
                    $q->orWhereIn($column, $this->openUpcoming()
                        ->select($column)
                        ->whereNotNull($column)
                        ->groupBy($column)
                        ->havingRaw('count(*) >= ?', [$threshold]));
                }
            });
    }

    /**
     * The other open, upcoming bookings sharing a signal with this one,
     * each with a `matched_signals` attribute (labels).
     *
     * @return Collection<int, Booking>
     */
    public function relatedTo(Booking $booking): Collection
    {
        $signals = [];

        foreach (array_keys(self::SIGNALS) as $column) {
            if (filled($booking->{$column})) {
                $signals[$column] = (string) $booking->{$column};
            }
        }

        if ($signals === []) {
            return new Collection();
        }

        $related = $this->openUpcoming()
            ->with(['customer', 'vehicle'])
            ->whereKeyNot($booking->id)
            ->where(function (Builder $q) use ($signals): void {
                foreach ($signals as $column => $value) {
                    $q->orWhere($column, $value);
                }
            })
            ->orderBy('start_at')
            ->limit(self::RELATED_LIMIT)
            ->get();

        foreach ($related as $other) {
            $matched = [];

            foreach ($signals as $column => $value) {
                if ((string) $other->{$column} === $value) {
                    $matched[] = self::SIGNALS[$column];
                }
            }

            $other->setAttribute('matched_signals', $matched);
        }

        return $related;
    }

    public function threshold(): int
    {
        return (int) config('booking.suspicious_threshold', 3);
    }

    public function isOpenAndUpcoming(Booking $booking): bool
    {
        return in_array($booking->status, Booking::OPEN_STATUSES, true)
            && $booking->end_at->greaterThanOrEqualTo(today());
    }

    private function openUpcoming(): Builder
    {
        return Booking::query()
            ->whereIn('status', Booking::OPEN_STATUSES)
            ->where('end_at', '>=', today());
    }
}
