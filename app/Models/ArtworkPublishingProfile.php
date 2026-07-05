<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtworkPublishingProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'artwork_id',
        'short_description',
        'product_description',
        'story_inspiration',
        'materials_process',
    ];

    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    public function hasContent(): bool
    {
        foreach (['short_description', 'product_description', 'story_inspiration', 'materials_process'] as $field) {
            if (trim((string) ($this->{$field} ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }
}
