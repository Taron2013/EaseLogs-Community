<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArtworkCsvImportRequest;
use App\Models\Artwork;
use App\Services\ArtworkCsvService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArtworkCsvController extends Controller
{
    public function __construct(
        private readonly ArtworkCsvService $csvService,
    ) {}

    public function importExport(): View
    {
        return view('artworks.import-export');
    }

    public function export(): StreamedResponse
    {
        $artworks = Artwork::query()
            ->orderBy('id')
            ->get();

        return $this->csvService->downloadResponse($artworks);
    }

    public function import(ArtworkCsvImportRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $result = $this->csvService->import($request->file('csv'), $user);
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('artworks.import-export')
                ->with('error', $exception->getMessage());
        }

        $count = $result['created'];

        return redirect()
            ->route('artworks.import-export')
            ->with('success', $count === 1
                ? 'Imported 1 artwork from CSV.'
                : "Imported {$count} artworks from CSV.");
    }
}
