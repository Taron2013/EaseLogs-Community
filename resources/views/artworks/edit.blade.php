@extends('layouts.app')

@section('title', 'Edit: ' . $artwork->displayTitle() . ' — ' . config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">Edit artwork</h2>
    <p class="page-intro">Update the artwork details below.</p>

    <form method="POST" action="{{ route('artworks.update', $artwork) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('artworks._form')

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('artworks.show', $artwork) }}" class="btn">Cancel</a>
        </div>
    </form>

    @include('artworks._publishing_profile_form', ['artwork' => $artwork])
@endsection
