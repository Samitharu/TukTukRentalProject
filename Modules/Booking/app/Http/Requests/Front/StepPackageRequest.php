<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Front;

use Illuminate\Foundation\Http\FormRequest;

final class StepPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', 'exists:packages,id'],
        ];
    }
}
