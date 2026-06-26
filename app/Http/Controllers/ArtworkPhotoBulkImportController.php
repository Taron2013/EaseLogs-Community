<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArtworkPhotoBulkImportApplyRequest;
use App\Http\Requests\ArtworkPhotoBulkImportPreviewRequest;
use App\Http\Requests\ArtworkPhotoBulkImportResolveRequest;
use App\Http\Requests\ArtworkPhotoBulkImportUndoResolveRequest;
use App\Services\ArtworkPhotoBulkImportService;
use App\Support\ArtworkPhotoBulkImport\PhotoImportPreviewThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ArtworkPhotoBulkImportController extends Controller
{
    public function __construct(
        private readonly ArtworkPhotoBulkImportService $bulkImportService,
        private readonly PhotoImportPreviewThumbnailService $thumbnailService,
    ) {}

    public function preview(ArtworkPhotoBulkImportPreviewRequest $request): RedirectResponse
    {
        try {
            $result = $this->bulkImportService->preview(
                $request->user(),
                $request->file('photo_zip'),
                $request->file('mapping_csv'),
            );
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('artworks.import-export')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('artworks.photo-bulk-import.preview.show', ['token' => $result['token']]);
    }

    public function showPreview(string $token): View|RedirectResponse
    {
        $preview = $this->bulkImportService->cachedPreview(request()->user(), $token);

        if ($preview === null) {
            return redirect()
                ->route('artworks.import-export')
                ->with('error', 'This photo import preview expired. Upload again.');
        }

        return view('artworks.photo-bulk-import-preview', [
            'token' => $token,
            'preview' => $preview,
        ]);
    }

    public function thumbnail(string $token, string $rowKey): Response
    {
        return $this->thumbnailService->respond(request()->user(), $token, $rowKey);
    }

    public function searchArtworks(Request $request, string $token): JsonResponse
    {
        try {
            $this->bulkImportService->cachedPreview($request->user(), $token)
                ?? throw new \InvalidArgumentException('This photo import preview expired. Upload again.');

            $artworks = $this->bulkImportService->searchArtworksForManualResolve(
                $request->user(),
                $request->query('q'),
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['artworks' => $artworks]);
    }

    public function resolveMatch(ArtworkPhotoBulkImportResolveRequest $request, string $token): JsonResponse
    {
        try {
            $result = $this->bulkImportService->resolveRowManually(
                $request->user(),
                $token,
                $request->validated('row_key'),
                (int) $request->validated('artwork_id'),
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function undoResolve(ArtworkPhotoBulkImportUndoResolveRequest $request, string $token): JsonResponse
    {
        try {
            $result = $this->bulkImportService->undoManualResolve(
                $request->user(),
                $token,
                $request->validated('row_key'),
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function apply(ArtworkPhotoBulkImportApplyRequest $request): RedirectResponse
    {
        try {
            $result = $this->bulkImportService->apply(
                $request->user(),
                $request->validated('token'),
                $request->validated('confirm_rows') ?? [],
            );
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('artworks.import-export')
                ->with('error', $exception->getMessage());
        }

        $message = "Imported {$result['applied']} photo(s). Skipped {$result['skipped']} row(s).";

        if (($result['unconfirmed'] ?? 0) > 0) {
            $message .= " {$result['unconfirmed']} unconfirmed title match(es) were not imported.";
        }

        return redirect()
            ->route('artworks.import-export')
            ->with('success', $message);
    }

    public function discard(string $token): RedirectResponse
    {
        $this->bulkImportService->discard(request()->user(), $token);

        return redirect()
            ->route('artworks.import-export')
            ->with('success', 'Photo import preview discarded.');
    }
}
