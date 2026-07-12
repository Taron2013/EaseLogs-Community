<?php

namespace App\Http\Controllers\Concerns;

use App\Support\EaseLogsEdition;

trait RequiresArtworkTagAdmin
{
    protected function requireArtworkTagAdmin(): void
    {
        if (! EaseLogsEdition::supportsArtworkTagAdmin()) {
            abort(404);
        }
    }
}
