<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ArtworkBulkDeleteRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:artworks,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one artwork to delete.',
            'ids.min' => 'Select at least one artwork to delete.',
            'ids.*.exists' => 'One or more selected artworks could not be found.',
        ];
    }

    /**
     * @return list<int>
     */
    public function artworkIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids');

        return array_values(array_map('intval', $ids));
    }

    /**
     * @return array<string, string>
     */
    public function indexQueryParams(): array
    {
        $params = [];

        foreach (['filter', 'sort', 'direction', 'medium', 'tag', 'dimension_unit', 'width_min', 'width_max', 'height_min', 'height_max', 'q', 'page'] as $key) {
            $value = $this->input($key);

            if (is_string($value) && trim($value) !== '') {
                $params[$key] = trim($value);
            } elseif (is_numeric($value) && $key === 'page') {
                $params[$key] = (string) (int) $value;
            }
        }

        return $params;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('artworks.index', $this->indexQueryParams())
                ->withErrors($validator)
        );
    }
}
