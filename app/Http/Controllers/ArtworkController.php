<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArtworkRequest;
use App\Models\Artwork;
use App\Models\User;
use App\Services\ArtworkPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ArtworkController extends Controller
{
    public function __construct(
        private readonly ArtworkPhotoService $photoService,
    ) {}

    public function index(): View
    {
        $artworks = Artwork::query()
            ->with('latestPhoto')
            ->latest()
            ->paginate(20);

        return view('artworks.index', compact('artworks'));
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

        if ($request->hasFile('photo')) {
            $this->photoService->store($artwork, $request->file('photo'));
        }

        return redirect()
            ->route('artworks.index')
            ->with('success', 'Artwork created successfully.');
    }

    public function show(Artwork $artwork): View
    {
        $artwork->load('latestPhoto');

        return view('artworks.show', compact('artwork'));
    }

    public function edit(Artwork $artwork): View
    {
        $artwork->load('latestPhoto');

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

        if ($request->hasFile('photo')) {
            $this->photoService->store($artwork, $request->file('photo'));
        }

        return redirect()
            ->route('artworks.show', $artwork)
            ->with('success', 'Artwork updated successfully.');
    }

    public function destroy(Artwork $artwork): RedirectResponse
    {
        $this->photoService->deletePhotosForArtwork($artwork);
        $artwork->delete();

        return redirect()
            ->route('artworks.index')
            ->with('success', 'Artwork deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareArtworkData(array $data, ?User $user, ?Artwork $artwork = null): array
    {
        unset($data['photo'], $data['completed_work'], $data['confirm_completed_photo_upload']);

        $data['user_id'] = $user?->id ?? $artwork?->user_id;

        return $data;
    }
}
