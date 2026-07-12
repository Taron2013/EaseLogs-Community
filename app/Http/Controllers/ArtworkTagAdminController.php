<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresArtworkTagAdmin;
use App\Http\Requests\ArtworkTagStoreRequest;
use App\Http\Requests\ArtworkTagUpdateRequest;
use App\Models\ArtworkTag;
use App\Services\ArtworkTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ArtworkTagAdminController extends Controller
{
    use RequiresArtworkTagAdmin;

    public function __construct(
        private readonly ArtworkTagService $tagService,
    ) {}

    public function index(): View
    {
        $this->requireArtworkTagAdmin();

        $user = request()->user();

        return view('settings.artwork-tags.index', [
            'tags' => $this->tagService->userTagsWithUsage($user),
        ]);
    }

    public function store(ArtworkTagStoreRequest $request): RedirectResponse
    {
        $this->requireArtworkTagAdmin();

        $user = $request->user();
        $validated = $request->validated();

        $this->tagService->createTagForUser(
            $user,
            $validated['name'],
            \App\Support\ArtworkTagType::GENERAL,
        );

        return redirect()
            ->route('settings.artwork-tags.index')
            ->with('success', 'Tag created.');
    }

    public function update(ArtworkTagUpdateRequest $request, ArtworkTag $artwork_tag): RedirectResponse
    {
        $this->requireArtworkTagAdmin();
        $this->authorizeTag($artwork_tag, $request->user());

        $validated = $request->validated();

        $this->tagService->updateTagNameForUser(
            $artwork_tag,
            $validated['name'],
        );

        return redirect()
            ->route('settings.artwork-tags.index')
            ->with('success', 'Tag updated.');
    }

    public function destroy(ArtworkTag $artwork_tag): RedirectResponse
    {
        $this->requireArtworkTagAdmin();
        $this->authorizeTag($artwork_tag, request()->user());

        if (! $this->tagService->deleteTagIfUnused($artwork_tag)) {
            return redirect()
                ->route('settings.artwork-tags.index')
                ->with('error', 'This tag is assigned to artwork and cannot be deleted.');
        }

        return redirect()
            ->route('settings.artwork-tags.index')
            ->with('success', 'Tag deleted.');
    }

    private function authorizeTag(ArtworkTag $tag, ?\App\Models\User $user): void
    {
        if ($user === null || ! $this->tagService->tagBelongsToUser($tag, $user)) {
            abort(404);
        }
    }
}
