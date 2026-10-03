<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pricing.manage') ?? false;
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id;

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('coupons', 'code')->ignore($couponId)],
            'type' => ['required', Rule::in(['fixed', 'percent'])],
            'value' => ['required', 'numeric', 'min:0'],
            'min_days' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
