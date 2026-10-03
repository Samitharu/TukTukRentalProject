<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Package\Models\Addon;

/**
 * @property int $booking_id
 * @property int $addon_id
 * @property int $quantity
 * @property float $unit_price
 */
final class BookingAddon extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'addon_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
