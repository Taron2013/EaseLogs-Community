@extends('layouts.app')

@section('title', 'Settings — Artwork Tags — ' . config('easelogs.display_name'))

@section('content')
    <h2 class="page-heading">Settings / Artwork Tags</h2>
    <p class="page-intro">
        Create and manage tags before assigning them to artwork. Tags appear in artwork forms and filters.
    </p>

    @if ($errors->any())
        <div class="errors" role="alert" aria-live="polite">
            <strong>There were some problems with your submission.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="form-section panel-card" style="margin-bottom:1.5rem;">
        <h2 style="margin-top:0;font-size:1rem;">Create tag</h2>
        <form method="POST" action="{{ route('settings.artwork-tags.store') }}" class="artwork-tag-admin-create">
            @csrf
            <div class="artwork-tag-admin-create-fields">
                <div class="field">
                    <label for="tag_name">Name</label>
                    <input type="text" name="name" id="tag_name" value="{{ old('name') }}" maxlength="255" required autocomplete="off">
                </div>
                <div class="field artwork-tag-admin-create-action">
                    <button type="submit" class="btn btn-primary">Add tag</button>
                </div>
            </div>
        </form>
    </section>

    <section class="form-section panel-card artwork-tag-admin-group" style="margin-bottom:1.25rem;">
        <h2 style="margin-top:0;font-size:1rem;">Tags</h2>

        @if ($tags->isEmpty())
            <p class="field-hint" style="margin:0;">No tags yet.</p>
        @else
            <div class="artwork-table-wrap">
                <table class="artwork-tag-admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Usage</th>
                            <th class="artwork-actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tags as $tag)
                            <tr>
                                <td>
                                    <form id="artwork-tag-edit-{{ $tag->id }}" method="POST" action="{{ route('settings.artwork-tags.update', $tag) }}" class="artwork-tag-admin-edit-form">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="name" value="{{ old('name', $tag->name) }}" maxlength="255" required aria-label="Tag name for {{ $tag->name }}">
                                    </form>
                                </td>
                                <td>{{ $tag->artworks_count }} artwork{{ $tag->artworks_count === 1 ? '' : 's' }}</td>
                                <td class="artwork-actions-col">
                                    <button type="submit" class="btn" form="artwork-tag-edit-{{ $tag->id }}">Save</button>
                                    @if ($tag->artworks_count === 0)
                                        <form method="POST" action="{{ route('settings.artwork-tags.destroy', $tag) }}" onsubmit="return confirm('Delete this tag? It is not assigned to any artwork.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    @else
                                        <span class="field-hint">In use</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
