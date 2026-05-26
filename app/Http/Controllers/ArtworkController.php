<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArtworkRequest;
use App\Models\Artwork;
use App\Models\User;
use App\Services\ArtworkPhotoService;
use App\Services\SKUGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ArtworkController extends Controller
{
    public function __construct(
        private readonly SKUGenerator $skuGenerator,
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

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->ensureUserExists()) {
            return $redirect;
        }

        return view('artworks.create', [
            'artwork' => new Artwork,
            'statuses' => Artwork::STATUSES,
            'conditions' => Artwork::CONDITIONS,
        ]);
    }

    public function store(ArtworkRequest $request): RedirectResponse
    {
        if ($redirect = $this->ensureUserExists()) {
            return $redirect;
        }

        $user = User::query()->first();
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
            'statuses' => Artwork::STATUSES,
            'conditions' => Artwork::CONDITIONS,
        ]);
    }

    public function update(ArtworkRequest $request, Artwork $artwork): RedirectResponse
    {
        $user = User::query()->find($artwork->user_id) ?? User::query()->first();
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
        unset($data['photo']);

        // Map finished_painting to status
        if (!empty($data['finished_painting'])) {
            $data['status'] = 'in_inventory';
        } elseif (! array_key_exists('status', $data)) {
            $data['status'] = 'in_progress';
        }

        // Enforce finished_date semantics: only save when artwork is complete.
        $isComplete = ($data['status'] ?? 'in_progress') !== 'in_progress';

        if ($isComplete) {
            if (blank($data['finished_date'] ?? null)) {
                $data['finished_date'] = now()->format('Y-m-d');
            }
        } else {
            $data['finished_date'] = null;
            $data['finished_date_is_estimated'] = false;
        }

        // Generate context (uses finished_date for SKU generation when present)
        $context = $this->generatorContext($data, $user, $artwork);

        if (blank($data['inventory_code'] ?? null)) {
            $data['inventory_code'] = $this->skuGenerator->generateInventoryCode($context);
        }

        if (blank($data['sku'] ?? null)) {
            $data['sku'] = $this->skuGenerator->generateArtworkSKU($context);
        }

        $featureEnabled = config('artdoc.enable_professional_reproduction_photo_tracking', true);

        if (! $featureEnabled) {
            unset($data['professional_art_reproduction_photo']);
        } else {
            $data['professional_art_reproduction_photo'] = $isComplete
                ? ! empty($data['professional_art_reproduction_photo'])
                : false;
        }

        $data['user_id'] = $user?->id ?? $artwork?->user_id;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function generatorContext(array $data, ?User $user, ?Artwork $artwork = null): array
    {
        $context = [
            'medium' => $data['medium'] ?? null,
            'category' => $data['category'] ?? null,
            'finished_date' => $data['finished_date'] ?? null,
            'reference_date' => $data['started_date'] ?? now(),
        ];

        if ($user) {
            $context['user'] = $user;
            $context['user_name'] = $user->name;
        }

        if ($artwork) {
            $context['exclude_artwork_id'] = $artwork->id;
        }

        return $context;
    }

    private function ensureUserExists(): ?RedirectResponse
    {
        if (User::query()->exists()) {
            return null;
        }

        return redirect()
            ->route('artworks.index')
            ->with('error', 'Create a user account before adding artworks. Run: php artisan tinker — then User::factory()->create()');
    }
}
