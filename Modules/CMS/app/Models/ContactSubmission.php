<?php

declare(strict_types=1);

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;

final class ContactSubmission extends Model
{
    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'locale',
        'ip',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }
}
