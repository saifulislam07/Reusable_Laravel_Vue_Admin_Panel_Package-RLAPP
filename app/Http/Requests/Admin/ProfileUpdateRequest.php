<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user((string) config('admin-kit.guard'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            // Only required when the email is actually changing -- renaming
            // yourself shouldn't need it, but changing where account-recovery
            // mail goes is exactly the kind of change a hijacked session
            // could otherwise make unchallenged.
            'current_password' => $this->input('email') !== $user->email
                ? ['required', 'current_password:'.config('admin-kit.guard')]
                : ['sometimes'],
        ];
    }
}
