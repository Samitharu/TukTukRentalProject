<?php

declare(strict_types=1);

namespace Modules\Availability\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Availability\Models\TransferArea;
use Modules\Localization\Support\TranslatableRules;

/** Shared by create and edit of an airport pickup destination area. */
final class TransferAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $area = $this->route('area');

        return $area instanceof TransferArea
            ? ($this->user()?->can('update', $area) ?? false)
            : ($this->user()?->can('create', TransferArea::class) ?? false);
    }

    public function rules(): array
    {
        return [
            ...TranslatableRules::forField('name', max: 120),
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            // There and back, so the vehicle's next trip can't be booked
            // before it is free again. 15 min to 16 h.
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:960'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
