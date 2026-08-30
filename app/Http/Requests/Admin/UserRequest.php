<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Admin\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
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
        /** @var User|null $editing */
        $editing = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($editing?->id),
            ],

            // Blank on update means "leave the current password alone" --
            // same convention as SmtpSettingsRequest. On create, blank is
            // only valid when inviting: sendInvite() below generates one
            // instead, and the user sets their own via that email. Exactly
            // one of required/nullable, never both -- Laravel's "required"
            // still rejects a missing value even alongside "nullable", so
            // mixing them isn't a hard bug, but it reads as if this were
            // simultaneously optional and mandatory.
            'password' => [
                $editing || $this->boolean('invite') ? 'nullable' : 'required',
                'string', Password::defaults(),
            ],
            'invite' => ['sometimes', 'boolean'],

            'status' => ['required', Rule::enum(UserStatus::class)],
            'avatar_media_id' => ['nullable', 'integer', 'exists:media,id'],

            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function sendInvite(): bool
    {
        return $this->route('user') === null && $this->boolean('invite');
    }
}
