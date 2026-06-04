@props(['artwork'])

<div class="artwork-actions-stack">
    <a href="{{ route('artworks.show', $artwork) }}" class="artwork-action-link">View</a>
    <a href="{{ route('artworks.edit', $artwork) }}" class="artwork-action-link">Edit</a>
    @unless ($easelogsDemo['blocks_deletes'] ?? false)
        <form method="POST"
              action="{{ route('artworks.destroy', $artwork) }}"
              class="artwork-action-delete-form"
              onsubmit="return confirm('Delete this artwork? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="artwork-action-delete">Delete</button>
        </form>
    @endunless
</div>
