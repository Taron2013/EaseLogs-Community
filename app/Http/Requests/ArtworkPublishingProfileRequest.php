<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArtworkPublishingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'short_description' => ['nullable', 'string', 'max:5000'],
            'product_description' => ['nullable', 'string', 'max:20000'],
            'story_inspiration' => ['nullable', 'string', 'max:20000'],
            'materials_process' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
