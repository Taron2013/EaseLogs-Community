<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArtworkCsvImportRequest extends FormRequest
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
            'csv' => [
                'required',
                'file',
                'mimes:csv,txt',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
                'max:2048',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv.required' => 'Choose a CSV file to import.',
            'csv.mimes' => 'The import file must be a CSV (.csv) file.',
            'csv.max' => 'The CSV file may not be larger than 2 MB.',
        ];
    }
}
