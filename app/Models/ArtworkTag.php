<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ArtworkTag extends Model
{
    /** @use HasFactory<\Database\Factories\ArtworkTagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    public function artworks(): BelongsToMany
    {
        return $this->belongsToMany(Artwork::class, 'artwork_tag');
    }
}
