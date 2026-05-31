<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

trait EnsuresCommunityUser
{
    protected function ensureUserExists(string $redirectRoute = 'artworks.index'): ?RedirectResponse
    {
        if (User::query()->exists()) {
            return null;
        }

        return redirect()
            ->route($redirectRoute)
            ->with('error', $this->noUserSetupMessage());
    }

    protected function noUserSetupMessage(): string
    {
        return 'No user account exists yet. Run php artisan db:seed to create the default Community account (admin@easelogs.local).';
    }
}
