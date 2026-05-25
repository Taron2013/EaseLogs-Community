@extends('layouts.app')

@section('title', 'New artwork — ' . config('app.name'))

@section('content')
    <h2 class="page-heading">Quick artwork entry</h2>
    <p class="page-intro">Create a placeholder inventory entry quickly. Fill in the full artwork details later on the Edit page.</p>

    <form method="POST" action="{{ route('artworks.store') }}">
        @csrf
        @include('artworks._quick_entry_form')

        <div class="actions">
            <button type="submit" class="btn btn-primary">Create artwork</button>
            <a href="{{ route('artworks.index') }}" class="btn">Cancel</a>
        </div>
    </form>
@endsection
