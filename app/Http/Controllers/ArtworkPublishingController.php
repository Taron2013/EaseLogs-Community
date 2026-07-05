<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArtworkPublishingProfileRequest;
use App\Models\Artwork;
use App\Services\ArtworkPublishingProfileService;
use Illuminate\Http\RedirectResponse;

class ArtworkPublishingController extends Controller
{
    public function __construct(
        private readonly ArtworkPublishingProfileService $publishingService,
    ) {}

    public function update(ArtworkPublishingProfileRequest $request, Artwork $artwork): RedirectResponse
    {
        $this->publishingService->syncForArtwork($artwork, $request->validated());

        return redirect()
            ->route('artworks.edit', $artwork)
            ->with('success', 'Publishing copy saved.')
            ->withFragment('publishing');
    }
}
