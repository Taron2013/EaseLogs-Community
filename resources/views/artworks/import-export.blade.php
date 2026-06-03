@extends('layouts.app')

@section('title', 'Import / Export — ' . config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">Import / Export</h2>
    <p class="page-intro">
        Import or export artwork metadata as CSV. This does not move photos — add images from each artwork’s create or edit screen.
    </p>

    @include('artworks._csv_import_export')
@endsection
