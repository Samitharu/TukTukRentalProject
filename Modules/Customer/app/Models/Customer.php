<?php

declare(strict_types=1);

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Modules\Booking\Models\Booking;

/**
 * Guest-checkout by default (`password` null until the customer sets one
 * from the set-password link sent after booking — Phase 5). Deliberately a
 * separate model/table/guard from the staff `User` model: see the note on
 * `App\Models\User`.
 *
 * @property int $id
 * @property string $email
 * @property string|null $phone
 * @property string $full_name
 * @property string|null $nationality
 * @property string|null $passport_number
 * @property string|null $locale_preference
 */
final class Customer extends Model
{
    /** @use HasFactory<\Modules\Customer\Database\Factories\CustomerFactory> */
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'email',
        'phone',
        'full_name',
        'nationality',
        'passport_number',
        'password',
        'locale_preference',
    ];

    protected $hidden = [
        'password',
        'passport_number',
    ];

    protected function casts(): array
    {
        return [
            'passport_number' => 'encrypted',
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
