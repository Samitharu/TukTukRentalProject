<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $ip
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $blocked_until
 */
final class BlockedIp extends Model
{
    protected $fillable = [
        'ip',
        'reason',
        'blocked_until',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'blocked_until' => 'datetime',
        ];
    }

    public function scopeCurrentlyBlocking(Builder $query, string $ip): Builder
    {
        return $query->where('ip', $ip)
            ->where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()));
    }

    public static function isBlocked(string $ip): bool
    {
        return self::query()->currentlyBlocking($ip)->exists();
    }
}
