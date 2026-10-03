<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Front;

use Illuminate\Foundation\Http\FormRequest;

final class StepAddonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'addons' => ['nullable', 'array'],
            'addons.*' => ['integer', 'min:0', 'max:20'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }
}
