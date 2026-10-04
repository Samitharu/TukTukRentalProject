<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Fleet\Models\Vehicle;

final class ReassignVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
        ];
    }

    /**
     * A booking's dates mean rental days for a tuk tuk but nights for a
     * stay, so it can only move to a unit of the same kind.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('vehicle_id')) {
                    return;
                }

                $target = Vehicle::query()->with('category')->find($this->integer('vehicle_id'));

                if ($target !== null && $target->kind() !== $this->route('booking')->vehicle->kind()) {
                    $validator->errors()->add('vehicle_id', __('A booking can only move to a unit of the same type (tuk tuk ↔ tuk tuk, stay ↔ stay).'));
                }
            },
        ];
    }
}
