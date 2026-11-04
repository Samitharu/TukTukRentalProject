<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $booking_id
 * @property string $type
 * @property float $amount
 * @property float|null $amount_eur
 * @property string|null $notes
 */
final class BookingExtraCharge extends Model
{
    /** Km beyond the booking's allowance — kept in step with the odometer by BookingService::recordOdometer(). */
    public const string TYPE_EXTRA_KM = 'extra_km';

    protected $fillable = [
        'booking_id',
        'type',
        'amount',
        'amount_eur',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_eur' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
