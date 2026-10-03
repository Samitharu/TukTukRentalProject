<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pricing.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'rate' => ['required', 'numeric', 'min:0.000001'],
        ];
    }
}
