<?php

declare(strict_types=1);

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $booking_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $changed_by
 * @property string|null $reason
 */
final class BookingStatusHistory extends Model
{
    public const UPDATED_AT = null;

    // Eloquent's default guess would be "booking_status_histories"
    // (pluralizing "History" as "Histories"); the migration uses the
    // schema doc's exact name.
    protected $table = 'booking_status_history';

    protected $fillable = [
        'booking_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by');
    }
}
