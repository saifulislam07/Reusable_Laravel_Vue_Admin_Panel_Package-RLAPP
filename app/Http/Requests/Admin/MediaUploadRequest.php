<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
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
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'required', 'file',
                'max:'.(int) (config('media-library.max_file_size', 10 * 1024 * 1024) / 1024),
            ],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ];
    }
}
