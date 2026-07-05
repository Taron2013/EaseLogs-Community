<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArtworkBulkDeleteRequest;
use App\Http\Requests\ArtworkRequest;
use App\Models\Artwork;
use App\Models\User;
use App\Support\ArtworkIndexQuery;
use App\Support\ArtworkStartDate;
use App\Support\DemoMode;
use App\Services\ArtworkMediumSuggestionService;
use App\Services\ArtworkPhotoService;
use App\Services\ArtworkTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArtworkController extends Controller
{
    public function __construct(
        private readonly ArtworkMediumSuggestionService $mediumSuggestionService,
        private readonly ArtworkPhotoService $photoService,
        private readonly ArtworkTagService $tagService,
    ) {}

    public function index(Request $request): View
    {
        $listing = ArtworkIndexQuery::fromRequest($request);
        $user = $request->user();

        $artworks = $listing->baseQuery()
            ->with(['latestPhoto', 'tags'])
            ->tap(fn ($query) => $listing->applyTo($query, $user->id))
            ->paginate(20)
            ->withQueryString();

        return view('artworks.index', [
            'artworks' => $artworks,
            'listing' => $listing,
            'filters' => $listing->filters(),
            'search' => $listing->search(),
            'sort' => $listing->sort(),
            'mediums' => $this->mediumSuggestionService->filterOptions($user),
            'tags' => \App\Models\ArtworkTag::query()
                ->where('user_id', $user->id)
                ->orderBy('name')
                ->pluck('name'),
            'dimensionUnits' => Artwork::query()
                ->whereNotNull('dimension_unit')
                ->where('dimension_unit', '!=', '')
                ->distinct()
                ->orderBy('dimension_unit')
                ->pluck('dimension_unit'),
        ]);
    }

    public function create(): View
    {
        return view('artworks.create', [
            'artwork' => new Artwork,
        ]);
    }

    public function store(ArtworkRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->prepareArtworkData($request->validated(), $user);

        $artwork = Artwork::create($data);

        if ($request->has('tags')) {
            $this->tagService->syncForArtwork(
                $artwork,
                $user,
                $this->tagService->parseTagInput($request->input('tags')),
            );
        }

        if ($request->hasFile('photo')) {
            $this->photoService->store($artwork, $request->file('photo'));
        }

        return $this->redirectAfterArtworkStore($request, $artwork);
    }

    public function show(Artwork $artwork): View
    {
        $artwork->load(['latestPhoto', 'publishingProfile']);

        return view('artworks.show', compact('artwork'));
    }

    public function edit(Artwork $artwork): View
    {
        $artwork->load(['latestPhoto', 'tags', 'publishingProfile']);

        return view('artworks.edit', [
            'artwork' => $artwork,
        ]);
    }

    public function update(ArtworkRequest $request, Artwork $artwork): RedirectResponse
    {
        $user = User::query()->find($artwork->user_id) ?? $request->user();
        $data = $this->prepareArtworkData(
            $request->validated(),
            $user,
            $artwork
        );

        $artwork->update($data);

        if ($request->has('tags')) {
            $this->tagService->syncForArtwork(
                $artwork,
                $user,
                $this->tagService->parseTagInput($request->input('tags')),
            );
        }

        if ($request->hasFile('photo')) {
            $this->photoService->store($artwork, $request->file('photo'));
        }

        return $this->redirectAfterArtworkUpdate($request, $artwork);
    }

    public function destroy(Artwork $artwork): RedirectResponse
    {
        $this->deleteArtwork($artwork);

        return redirect()
            ->route('artworks.index')
            ->with('success', 'Artwork deleted successfully.');
    }

    public function bulkDestroy(ArtworkBulkDeleteRequest $request): RedirectResponse
    {
        $ids = $request->artworkIds();

        $artworks = Artwork::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        foreach ($artworks as $artwork) {
            $this->deleteArtwork($artwork);
        }

        $count = $artworks->count();
        $message = $count === 1
            ? '1 artwork deleted.'
            : "{$count} artworks deleted.";

        return redirect()
            ->route('artworks.index', $request->indexQueryParams())
            ->with('success', $message);
    }

    private function deleteArtwork(Artwork $artwork): void
    {
        $this->photoService->deletePhotosForArtwork($artwork);
        $artwork->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareArtworkData(array $data, ?User $user, ?Artwork $artwork = null): array
    {
        unset($data['photo'], $data['completed_work'], $data['confirm_completed_photo_upload'], $data['tags']);

        if ($artwork === null) {
            $data = ArtworkStartDate::applyCreateDefault($data);
        }

        $data['user_id'] = $user?->id ?? $artwork?->user_id;

        return $data;
    }

    private function redirectAfterArtworkStore(Request $request, Artwork $artwork): RedirectResponse
    {
        $redirect = redirect()
            ->route('artworks.index')
            ->with('success', 'Artwork created successfully.');

        return $this->withDemoUploadDiscardNotice($request, $redirect);
    }

    private function redirectAfterArtworkUpdate(Request $request, Artwork $artwork): RedirectResponse
    {
        $redirect = redirect()
            ->route('artworks.show', $artwork)
            ->with('success', 'Artwork updated successfully.');

        return $this->withDemoUploadDiscardNotice($request, $redirect);
    }

    private function withDemoUploadDiscardNotice(Request $request, RedirectResponse $redirect): RedirectResponse
    {
        if (! DemoMode::uploadWasDiscarded($request)) {
            return $redirect;
        }

        return $redirect->with('info', DemoMode::MESSAGE_UPLOAD_DISCARDED);
    }
}
