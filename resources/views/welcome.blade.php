@extends('layouts.app')

@section('title', config('app.name', 'ArtDoc'))

@section('content')
    <h2 class="page-heading">{{ config('app.name', 'ArtDoc') }}</h2>
    <p class="page-intro">
        Local-first artwork inventory for artists. Catalog works, track lifecycle status,
        and manage inventory metadata from your own machine.
    </p>
    <p>
        <a href="{{ route('artworks.index') }}" class="btn btn-primary">Open artwork inventory</a>
        <a href="{{ route('artworks.create') }}" class="btn">New artwork</a>
    </p>
@endsection
