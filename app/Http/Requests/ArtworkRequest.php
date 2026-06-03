<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ArtworkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'completed_work' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'completed_date' => [
                'nullable',
                'date',
                Rule::prohibitedIf(fn (): bool => ! $this->boolean('completed_work')),
                Rule::when(
                    $this->boolean('completed_work') && $this->filled('start_date'),
                    ['after_or_equal:start_date']
                ),
            ],
            'artwork_type' => ['nullable', 'string', 'max:255'],
            'medium' => ['nullable', 'string', 'max:255'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'numeric', 'min:0'],
            'dimension_unit' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
            'confirm_completed_photo_upload' => ['nullable', 'boolean'],
            'photo' => [
                'nullable',
                'image',
                'mimes:'.implode(',', config('easelogs.photo_mimes', ['jpeg', 'jpg', 'png', 'webp'])),
                'max:'.config('easelogs.photo_max_kb', 10240),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                ! $this->boolean('completed_work')
                && trim((string) $this->input('completed_date', '')) !== ''
            ) {
                $validator->errors()->add(
                    'completed_date',
                    'Completed date is only allowed when the artwork is marked as completed work.'
                );
            }

            if (
                $this->boolean('completed_work')
                && $this->hasFile('photo')
                && ! $this->boolean('confirm_completed_photo_upload')
            ) {
                $validator->errors()->add(
                    'confirm_completed_photo_upload',
                    'Confirm that you understand this will replace or add a new image for a completed artwork.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');

        $this->merge([
            'title' => ($title === null || trim((string) $title) === '') ? '' : $title,
            'completed_work' => $this->boolean('completed_work'),
            'confirm_completed_photo_upload' => $this->boolean('confirm_completed_photo_upload'),
        ]);

        if (! $this->boolean('completed_work')) {
            if (trim((string) $this->input('completed_date', '')) === '') {
                $this->merge(['completed_date' => null]);
            }
        } elseif (! $this->filled('completed_date')) {
            $this->merge(['completed_date' => now()->format('Y-m-d')]);
        }
    }
}
