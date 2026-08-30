<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordUpdateRequest extends FormRequest
{
    // Profile, password, and logout-other-devices are three separate forms
    // on the same page and all three have a "current_password" field --
    // without named bags, a failed submission on one would bleed its error
    // onto the same-named field in the other two after the redirect back.
    protected $errorBag = 'updatePassword';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:'.config('admin-kit.guard')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
