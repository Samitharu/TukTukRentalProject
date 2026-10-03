<?php

declare(strict_types=1);

namespace Modules\Package\Services;

/**
 * A UNIQUE(package_id, min_days) database constraint stops two tiers
 * starting on the same day count, but it can't express "no gaps and no
 * overlaps across the whole range" — that continuity check lives here, run
 * by the admin package builder before tiers are saved (brief §5: "pricing
 * consistency (no overlapping seasonal ranges, tiers without gaps)").
 */
final class PackagePricingTierValidator
{
    /**
     * @param array<int, array{min_days: int, max_days: int|null}> $tiers
     * @return string[] Human-readable problems; empty array means valid.
     */
    public function validate(array $tiers): array
    {
        if ($tiers === []) {
            return ['At least one pricing tier is required.'];
        }

        $sorted = collect($tiers)->sortBy('min_days')->values();
        $errors = [];

        $openEndedCount = $sorted->filter(fn (array $tier) => $tier['max_days'] === null)->count();
        if ($openEndedCount > 1) {
            $errors[] = 'Only the last tier may be open-ended (no maximum days).';
        }

        foreach ($sorted as $index => $tier) {
            if ($tier['max_days'] !== null && $tier['max_days'] < $tier['min_days']) {
                $errors[] = "Tier starting at {$tier['min_days']} days has a maximum below its minimum.";

                continue;
            }

            $next = $sorted->get($index + 1);

            if ($next === null) {
                continue;
            }

            if ($tier['max_days'] === null) {
                $errors[] = "Tier starting at {$tier['min_days']} days is open-ended but is not the last tier.";

                continue;
            }

            $expectedNextMin = $tier['max_days'] + 1;

            if ($next['min_days'] !== $expectedNextMin) {
                $errors[] = $next['min_days'] > $expectedNextMin
                    ? "There is a gap between {$tier['max_days']} and {$next['min_days']} days."
                    : "Tiers ending at {$tier['max_days']} days and starting at {$next['min_days']} days overlap.";
            }
        }

        return $errors;
    }
}
