<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vehicle')) ?? false;
    }

    public function rules(): array
    {
        return UnitRules::rules(
            UnitRules::isStayCategory($this->input('category_id')),
            $this->route('vehicle')?->id,
        );
    }

    public function messages(): array
    {
        return UnitRules::messages();
    }
}
