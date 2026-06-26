@extends('layouts.app')

@section('title', 'Photo import preview — ' . config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">Photo import preview</h2>
    <p class="page-intro">
        Review thumbnails and matches before importing. Exact title and filename matches are selected by default; partial and manual matches require review.
        Use <strong>Resolve match</strong> for generic filenames. Uncheck any row you want to skip.
        Explicit <code>artwork_id</code> rows import automatically when you apply.
    </p>

    @include('artworks._photo_import_preview', [
        'preview' => $preview,
        'token' => $token,
        'supportsSkuSearch' => false,
    ])

    @include('artworks._photo_import_manual_resolve', ['token' => $token, 'supportsSkuSearch' => false])
@endsection
