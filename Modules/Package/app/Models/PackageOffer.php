<?php

declare(strict_types=1);

namespace Modules\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Pricing\Support\Money;

/**
 * Customers booking `package` are offered `offeredPackage`; taking it makes
 * `package` cheaper by the amount staff entered in each currency.
 *
 * @property int $id
 * @property int $package_id
 * @property int $offered_package_id
 * @property float $discount
 * @property float $discount_eur
 */
final class PackageOffer extends Model
{
    protected $fillable = [
        'package_id',
        'offered_package_id',
        'discount',
        'discount_eur',
    ];

    protected function casts(): array
    {
        return [
            'discount' => 'decimal:2',
            'discount_eur' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function offeredPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'offered_package_id');
    }

    public function displayDiscount(): string
    {
        return Money::dual($this->discount, $this->discount_eur);
    }
}
