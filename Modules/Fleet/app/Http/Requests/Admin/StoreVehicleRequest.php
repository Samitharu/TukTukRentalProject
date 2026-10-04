<?php

declare(strict_types=1);

namespace Modules\Fleet\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Fleet\Models\Vehicle;

final class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    public function rules(): array
    {
        return UnitRules::rules(UnitRules::isStayCategory($this->input('category_id')));
    }

    public function messages(): array
    {
        return UnitRules::messages();
    }
}
