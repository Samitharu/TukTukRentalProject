<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'role' => ['required', 'string', Rule::exists(Role::class, 'name')],
        ];
    }
}
