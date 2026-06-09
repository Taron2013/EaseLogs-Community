<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ArtworkStartDate
{
    /**
     * Default start_date on artwork creation when the user leaves it blank.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyCreateDefault(array $data): array
    {
        if (blank($data['start_date'] ?? null)) {
            $data['start_date'] = Carbon::now()->toDateString();
        }

        return $data;
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string|null>
     */
    public static function applyImportDefault(array $values): array
    {
        if (($values['start_date'] ?? null) === null && ($values['completed_date'] ?? null) !== null) {
            $values['start_date'] = $values['completed_date'];
        }

        return $values;
    }
}
