<?php

declare(strict_types=1);

namespace Modules\Package\Support;

use Modules\Package\Models\Package;

/**
 * The admin package form's one question — "How is it charged?" — and what
 * each answer means in the package's pricing columns. Staff never see
 * pricing models, tiers or day ranges; the form posts a `charge` and a
 * `price`, and this class turns them into the package attributes and its
 * single price tier.
 */
final class PackageCharge
{
    /** Rental: price × days. */
    public const string PER_DAY = 'per_day';

    /** Rental: price × hours, a same-day rental. */
    public const string PER_HOUR = 'per_hour';

    /** Rental: one price for N days including X km; km beyond that charged per km. */
    public const string KM_PACKAGE = 'km_package';

    /** Stay: price × nights. */
    public const string PER_NIGHT = 'per_night';

    /** Airport pickup: price per trip, added to the destination area's price. */
    public const string PER_TRIP = 'per_trip';

    /** Activity: price × people. */
    public const string PER_PERSON = 'per_person';

    /** Activity: one price. */
    public const string FIXED = 'fixed';

    /** Older packages only — kept so editing one never changes how it is priced. */
    public const string PER_WEEK = 'per_week';

    public const string PER_MONTH = 'per_month';

    /** @var array<string, string[]> the answers offered for each booking style */
    public const array BY_KIND = [
        Package::KIND_VEHICLE => [self::PER_DAY, self::PER_HOUR, self::KM_PACKAGE],
        Package::KIND_STAY => [self::PER_NIGHT],
        Package::KIND_TRANSFER => [self::PER_TRIP],
        Package::KIND_ACTIVITY => [self::PER_PERSON, self::FIXED],
        Package::KIND_TOUR => [self::PER_DAY],
    ];

    /** @var string[] the answers whose price can depend on group size instead */
    public const array GROUP_PRICED = [self::PER_HOUR, self::PER_DAY, self::KM_PACKAGE, self::FIXED];

    /** @var string[] */
    public const array ALL = [
        self::PER_DAY, self::PER_HOUR, self::KM_PACKAGE, self::PER_NIGHT, self::PER_TRIP,
        self::PER_PERSON, self::FIXED, self::PER_WEEK, self::PER_MONTH,
    ];

    /** The answer that describes an existing package, for the edit form. */
    public static function of(Package $package): string
    {
        return match (true) {
            $package->isStay() => self::PER_NIGHT,
            $package->isTransfer() => self::PER_TRIP,
            $package->isActivity() => $package->pricing_model === Package::MODEL_PER_PERSON ? self::PER_PERSON : self::FIXED,
            $package->pricing_model === Package::MODEL_PER_HOUR => self::PER_HOUR,
            $package->pricing_model === Package::MODEL_FIXED_BUNDLE => self::KM_PACKAGE,
            $package->pricing_model === Package::MODEL_PER_WEEK => self::PER_WEEK,
            $package->pricing_model === Package::MODEL_PER_MONTH => self::PER_MONTH,
            default => self::PER_DAY,
        };
    }

    /** Whether $charge may be used for a package of $kind (or is the package's own older one). */
    public static function allows(string $kind, string $charge, ?Package $existing = null): bool
    {
        return in_array($charge, self::BY_KIND[$kind] ?? [], true)
            || ($existing !== null && $kind === Package::KIND_VEHICLE && in_array($charge, [self::PER_WEEK, self::PER_MONTH], true) && self::of($existing) === $charge);
    }

    /**
     * The package columns for an answer. $input is the posted form; only
     * the fields that answer shows are read.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function attributes(string $charge, array $input): array
    {
        $none = [
            'min_hours' => null, 'max_hours' => null,
            'included_km' => null, 'extra_km_rate' => null, 'extra_km_rate_eur' => null, 'included_km_per_day' => true,
        ];
        $lengthRange = [
            'min_days' => self::number($input['min_days'] ?? null) ?? 1,
            'max_days' => self::number($input['max_days'] ?? null),
        ];
        $none['group_pricing'] = in_array($charge, self::GROUP_PRICED, true) && filter_var($input['group_pricing'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return match ($charge) {
            self::PER_DAY => [...$none, ...$lengthRange,
                'pricing_model' => Package::MODEL_PER_DAY,
                // Optional daily km limit (More options), blank = unlimited.
                'included_km' => self::number($input['daily_km'] ?? null),
                'extra_km_rate' => self::decimal($input['daily_extra_km_rate'] ?? null),
                'extra_km_rate_eur' => self::decimal($input['daily_extra_km_rate_eur'] ?? null),
                'included_km_per_day' => true,
            ],
            self::PER_HOUR => [...$none,
                'pricing_model' => Package::MODEL_PER_HOUR,
                'min_days' => 1, 'max_days' => 1,
                'min_hours' => self::number($input['min_hours'] ?? null) ?? 1,
                'max_hours' => self::number($input['max_hours'] ?? null),
            ],
            self::KM_PACKAGE => [...$none,
                'pricing_model' => Package::MODEL_FIXED_BUNDLE,
                'min_days' => $days = self::number($input['package_days'] ?? null) ?? 1,
                'max_days' => $days,
                'included_km' => self::number($input['included_km'] ?? null),
                'extra_km_rate' => self::decimal($input['extra_km_rate'] ?? null),
                'extra_km_rate_eur' => self::decimal($input['extra_km_rate_eur'] ?? null),
                'included_km_per_day' => false,
            ],
            self::PER_NIGHT => [...$none, ...$lengthRange, 'pricing_model' => Package::MODEL_PER_DAY],
            self::PER_TRIP => [...$none, 'pricing_model' => Package::MODEL_FIXED_BUNDLE, 'min_days' => 1, 'max_days' => null],
            self::PER_PERSON => [...$none, 'pricing_model' => Package::MODEL_PER_PERSON, 'min_days' => 1, 'max_days' => null],
            self::FIXED => [...$none, 'pricing_model' => Package::MODEL_FIXED_BUNDLE, 'min_days' => 1, 'max_days' => null],
            self::PER_WEEK => [...$none, ...$lengthRange, 'pricing_model' => Package::MODEL_PER_WEEK],
            self::PER_MONTH => [...$none, ...$lengthRange, 'pricing_model' => Package::MODEL_PER_MONTH],
        };
    }

    /** Label for the "How is it charged?" choice. */
    public static function label(string $charge): string
    {
        return match ($charge) {
            self::PER_DAY => __('Per day'),
            self::PER_HOUR => __('Per hour'),
            self::KM_PACKAGE => __('KM package — fixed price with km included'),
            self::PER_NIGHT => __('Per night'),
            self::PER_TRIP => __('Per trip'),
            self::PER_PERSON => __('Per person'),
            self::FIXED => __('Fixed price'),
            self::PER_WEEK => __('Per week'),
            self::PER_MONTH => __('Per month'),
        };
    }

    /** Label for the price box of each answer. */
    public static function priceLabel(string $charge): string
    {
        return match ($charge) {
            self::PER_DAY => __('Price per day'),
            self::PER_HOUR => __('Price per hour'),
            self::KM_PACKAGE => __('Package price'),
            self::PER_NIGHT => __('Price per night'),
            self::PER_TRIP => __('Price per trip (added to the destination area price)'),
            self::PER_PERSON => __('Price per person'),
            self::PER_WEEK => __('Price per week'),
            self::PER_MONTH => __('Price per month'),
            default => __('Price'),
        };
    }

    private static function number(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function decimal(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
