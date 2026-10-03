<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $email
 * @property string $ip
 * @property bool $successful
 * @property string|null $user_agent
 */
final class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'ip',
        'successful',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
        ];
    }

    public static function recentFailedCountFor(string $email, string $ip, int $minutes): int
    {
        return self::query()
            ->where('successful', false)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->where(fn ($q) => $q->where('email', $email)->orWhere('ip', $ip))
            ->count();
    }
}
