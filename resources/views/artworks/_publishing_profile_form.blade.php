@php
    $profile = $artwork->publishingProfile ?? new \App\Models\ArtworkPublishingProfile(['artwork_id' => $artwork->id]);
@endphp

<section id="publishing" class="form-section panel-card" aria-labelledby="publishing-heading" style="margin-top:1.5rem;">
    <h2 id="publishing-heading" style="margin-top:0;font-size:1.1rem;">Publishing</h2>
    <p class="field-hint" style="margin:0 0 1rem;">
        Public-facing copy for websites, marketplaces, and social posts. Separate from private studio notes.
    </p>

    <form method="POST" action="{{ route('artworks.publishing.update', $artwork) }}">
        @csrf
        @method('PATCH')

        <div class="field">
            <label for="short_description">Short description</label>
            <textarea name="short_description" id="short_description" rows="3" placeholder="Brief excerpt for cards and short listings">{{ old('short_description', $profile->short_description) }}</textarea>
        </div>

        <div class="field">
            <label for="product_description">Product description</label>
            <textarea name="product_description" id="product_description" rows="6" placeholder="Main reusable listing copy">{{ old('product_description', $profile->product_description) }}</textarea>
        </div>

        <div class="field">
            <label for="story_inspiration">Story / inspiration</label>
            <textarea name="story_inspiration" id="story_inspiration" rows="5" placeholder="Story, inspiration, or background">{{ old('story_inspiration', $profile->story_inspiration) }}</textarea>
        </div>

        <div class="field">
            <label for="materials_process">Materials &amp; process</label>
            <textarea name="materials_process" id="materials_process" rows="5" placeholder="Public materials and technique narrative">{{ old('materials_process', $profile->materials_process) }}</textarea>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save publishing copy</button>
        </div>
    </form>
</section>
