<?php

declare(strict_types=1);

namespace Modules\Pricing\Support;

use Modules\Pricing\Models\Currency;

/**
 * How amounts are written. Customers see USD and EUR; staff also see what each
 * of those is in LKR, at the two rates they set.
 */
final class Money
{
    public static function usd(float|string|null $amount): string
    {
        return self::format('$', (float) $amount);
    }

    public static function eur(float|string|null $amount): string
    {
        return self::format('€', (float) $amount);
    }

    /** "$25.00 / €21.50", or USD alone while there is no EUR price. */
    public static function dual(float|string|null $usd, float|string|null $eur): string
    {
        return $eur === null ? self::usd($usd) : self::usd($usd).' / '.self::eur($eur);
    }

    /** "$25.00 (LKR 7,500) / €21.50 (LKR 7,310)": staff screens and emails only. */
    public static function admin(float|string|null $usd, float|string|null $eur): string
    {
        $amounts = self::usd($usd).self::lkr($usd, Currency::lkrPerUsd());

        return $eur === null ? $amounts : $amounts.' / '.self::eur($eur).self::lkr($eur, Currency::lkrPerEur());
    }

    private static function format(string $symbol, float $amount): string
    {
        return ($amount < 0 ? '-' : '').$symbol.number_format(abs($amount), 2);
    }

    /** " (LKR 7,500)", or nothing until staff set the rate. */
    private static function lkr(float|string|null $amount, ?float $rate): string
    {
        return $rate === null ? '' : ' (LKR '.number_format((float) $amount * $rate).')';
    }
}
