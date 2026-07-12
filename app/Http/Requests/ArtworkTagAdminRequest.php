<?php

namespace App\Http\Requests;

use App\Models\ArtworkTag;
use App\Services\ArtworkTagService;
use App\Support\ArtworkTagType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class ArtworkTagAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeRules(): array
    {
        return ['prohibited'];
    }

    protected function duplicateType(): string
    {
        return ArtworkTagType::GENERAL;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();

            if ($user === null) {
                return;
            }

            $name = trim((string) $this->input('name'));
            $type = $this->duplicateType();
            $normalized = ArtworkTagService::normalizeName($name);
            $ignoreId = $this->duplicateCheckIgnoreTagId();

            $duplicate = ArtworkTag::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->where('normalized_name', $normalized)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add(
                    'name',
                    'A '.ArtworkTagType::label($type).' tag with this name already exists.',
                );
            }
        });
    }

    protected function duplicateCheckIgnoreTagId(): ?int
    {
        return null;
    }
}
