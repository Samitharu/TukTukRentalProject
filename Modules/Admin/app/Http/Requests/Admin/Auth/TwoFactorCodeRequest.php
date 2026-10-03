<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests\Admin\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
