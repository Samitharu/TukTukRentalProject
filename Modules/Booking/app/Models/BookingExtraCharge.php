<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $booking_id
 * @property string $type
 * @property float $amount
 * @property string|null $notes
 */
final class BookingExtraCharge extends Model
{
    protected $fillable = [
        'booking_id',
        'type',
        'amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
