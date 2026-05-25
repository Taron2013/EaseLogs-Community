@extends('layouts.app')

@section('title', 'Artworks — ' . config('app.name'))

@section('content')
    <p style="margin-top:0;">
        <a href="{{ route('artworks.create') }}" class="btn btn-primary">New artwork</a>
    </p>

    @if ($artworks->isEmpty())
        <p>No artworks yet. <a href="{{ route('artworks.create') }}">Create your first artwork</a>.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Inventory code</th>
                    <th>SKU</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($artworks as $artwork)
                    <tr>
                        <td><a href="{{ route('artworks.show', $artwork) }}">{{ $artwork->title ?: 'Untitled' }}</a></td>
                        <td>{{ $artwork->inventory_code }}</td>
                        <td>{{ $artwork->sku ?? '—' }}</td>
                        <td>{{ str_replace('_', ' ', $artwork->status) }}</td>
                        <td>{{ $artwork->updated_at?->format('Y-m-d') }}</td>
                        <td>
                            <a href="{{ route('artworks.edit', $artwork) }}">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pagination">
            {{ $artworks->links() }}
        </div>
    @endif
@endsection
