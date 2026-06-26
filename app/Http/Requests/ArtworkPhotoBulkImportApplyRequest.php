<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArtworkPhotoBulkImportApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],
            'confirm_rows' => ['nullable', 'array'],
            'confirm_rows.*' => ['string', 'max:255'],
        ];
    }
}
