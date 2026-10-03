<?php

declare(strict_types=1);

namespace Modules\Package\Services;

use Carbon\CarbonImmutable;

/**
 * Two seasons only truly conflict if their date ranges overlap *and* they
 * apply to at least one shared weekday — a "weekends only" season and a
 * "weekdays only" season are allowed to cover the same date range. Brief
 * §5: "pricing consistency (no overlapping seasonal ranges...)".
 */
final class PackageSeasonValidator
{
    /**
     * @param array<int, array{id?: int, starts_on: string, ends_on: string, weekday_mask: int}> $seasons
     * @return string[] Human-readable problems; empty array means valid.
     */
    public function validate(array $seasons): array
    {
        $errors = [];

        foreach ($seasons as $i => $a) {
            foreach ($seasons as $j => $b) {
                if ($j <= $i) {
                    continue;
                }

                if (($a['id'] ?? null) !== null && ($a['id'] ?? null) === ($b['id'] ?? null)) {
                    continue;
                }

                $aStart = CarbonImmutable::parse($a['starts_on']);
                $aEnd = CarbonImmutable::parse($a['ends_on']);
                $bStart = CarbonImmutable::parse($b['starts_on']);
                $bEnd = CarbonImmutable::parse($b['ends_on']);

                $datesOverlap = $aStart->lessThanOrEqualTo($bEnd) && $aEnd->greaterThanOrEqualTo($bStart);
                $weekdaysShared = ($a['weekday_mask'] & $b['weekday_mask']) !== 0;

                if ($datesOverlap && $weekdaysShared) {
                    $errors[] = "Seasons \"{$a['name']}\" and \"{$b['name']}\" overlap on shared weekdays within {$bStart->toDateString()}–{$aEnd->toDateString()}.";
                }
            }
        }

        return $errors;
    }
}
