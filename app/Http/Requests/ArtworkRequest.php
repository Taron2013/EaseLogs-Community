<?php

namespace App\Http\Requests;

use App\Models\Artwork;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArtworkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $artworkId = $this->route('artwork')?->id;

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'inventory_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('artworks', 'inventory_code')->ignore($artworkId),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('artworks', 'sku')->ignore($artworkId),
            ],
            'description' => ['nullable', 'string'],
            'started_date' => ['nullable', 'date'],
            'started_date_is_estimated' => ['nullable', 'boolean'],
            'finished_date' => [
                'nullable',
                'date',
                Rule::when(
                    $this->filled('started_date'),
                    ['after_or_equal:started_date']
                ),
            ],
            'finished_date_is_estimated' => ['nullable', 'boolean'],
            'finished_painting' => ['nullable', 'boolean'],
            'professional_art_reproduction_photo' => ['nullable', 'boolean'],
            'medium' => ['nullable', 'string', 'max:255'],
            'surface' => ['nullable', 'string', 'max:255'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'numeric', 'min:0'],
            'dimension_unit' => ['nullable', 'string', 'max:10'],
            'category' => ['nullable', 'string', 'max:255'],
            'style' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Artwork::STATUSES)],
            'condition' => ['nullable', Rule::in(Artwork::CONDITIONS)],
            'location' => ['nullable', 'string', 'max:255'],
            'storage_area' => ['nullable', 'string', 'max:255'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');

        $this->merge([
            'title' => ($title === null || trim((string) $title) === '') ? '' : $title,
            'started_date_is_estimated' => $this->boolean('started_date_is_estimated'),
            'finished_date_is_estimated' => $this->boolean('finished_date_is_estimated'),
            'finished_painting' => $this->boolean('finished_painting'),
            'professional_art_reproduction_photo' => $this->boolean('professional_art_reproduction_photo'),
        ]);
    }
}
