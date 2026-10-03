<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests\Front;

use Illuminate\Foundation\Http\FormRequest;

final class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            // Honeypot: a real visitor never sees or fills this field (hidden
            // via CSS, not `type="hidden"`, so basic bots that skip hidden
            // inputs but still fill visible-looking ones don't get a free
            // pass). Any value here means it's a bot — Rule::in([]) always fails.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => 'Something went wrong. Please try again.',
        ];
    }
}
