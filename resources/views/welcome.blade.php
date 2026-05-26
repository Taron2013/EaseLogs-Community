@extends('layouts.app')

@section('title', config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">{{ config('easelogs.display_name') }}</h2>
    <p class="page-intro">
        Self-hosted artwork inventory for hobbyist artists. Catalog works with photos,
        track completion dates, and keep your records on your own machine.
    </p>
    <p>
        <a href="{{ route('artworks.index') }}" class="btn btn-primary">Open artwork inventory</a>
        <a href="{{ route('artworks.create') }}" class="btn">New artwork</a>
    </p>
@endsection
