<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArtworkPhotoBulkImportResolveRequest extends FormRequest
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
            'row_key' => ['required', 'string', 'max:255'],
            'artwork_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
