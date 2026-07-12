<?php

namespace App\Http\Requests;

use App\Support\ArtworkTagType;

class ArtworkTagStoreRequest extends ArtworkTagAdminRequest
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
        return ArtworkTagType::GENERAL;
    }
}
