<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Admin\Menu;
use App\Support\Admin\MenuRouteOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
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
        /** @var Menu|null $editing */
        $editing = $this->route('menu');

        return [
            'label' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],

            // Exactly one of these two -- required_without on each covers
            // "at least one", prohibits covers "not both".
            'route' => [
                'nullable', 'string', 'required_without:url', 'prohibits:url',
                Rule::in(array_keys(MenuRouteOptions::all())),
            ],
            'url' => ['nullable', 'string', 'max:255', 'required_without:route'],

            'parent_id' => array_filter([
                'nullable',
                'integer',
                Rule::exists('menus', 'id'),
                // A menu can't be parented to itself. Descendants are
                // excluded from the picker itself (see
                // MenuController::formData()) rather than re-checked here,
                // since confirming "not a descendant" needs the whole tree,
                // not just this one row.
                $editing ? Rule::notIn([$editing->id]) : null,
            ]),

            'permission_name' => ['nullable', 'string', Rule::exists('permissions', 'name')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The full validated payload, normalised for Menu::create()/update():
     * `is_active` coerced from a checkbox's presence, and whichever of
     * route/url was not submitted made explicitly null (validated() would
     * otherwise just omit an absent, optional field instead of clearing it
     * on an update).
     *
     * @return array<string, mixed>
     */
    public function forSave(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        $data['is_active'] = $this->boolean('is_active');
        $data['route'] = $data['route'] ?? null;
        $data['url'] = $data['url'] ?? null;

        return $data;
    }
}
