<?php

namespace App\Http\Requests;

use App\Models\ArtworkTag;
use App\Support\ArtworkTagType;

class ArtworkTagUpdateRequest extends ArtworkTagAdminRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'type' => $this->typeRules(),
        ];
    }

    protected function duplicateType(): string
    {
        $tag = $this->route('artwork_tag');

        return $tag instanceof ArtworkTag
            ? ArtworkTagType::normalize($tag->type)
            : ArtworkTagType::GENERAL;
    }

    protected function duplicateCheckIgnoreTagId(): ?int
    {
        $tag = $this->route('artwork_tag');

        return $tag instanceof ArtworkTag ? $tag->id : null;
    }
}
