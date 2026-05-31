@extends('layouts.app')

@section('title', 'New artwork — ' . config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">New artwork</h2>
    <p class="page-intro">Add an artwork to your private inventory.</p>

    <form method="POST" action="{{ route('artworks.store') }}" enctype="multipart/form-data">
        @csrf
        @include('artworks._form')

        <div class="actions">
            <button type="submit" class="btn btn-primary">Create artwork</button>
            <a href="{{ route('artworks.index') }}" class="btn">Cancel</a>
        </div>
    </form>
@endsection
