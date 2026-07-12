<?php

namespace App\Models;

use App\Services\ArtworkTagService;
use App\Support\ArtworkTagType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ArtworkTag extends Model
{
    /** @use HasFactory<\Database\Factories\ArtworkTagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'normalized_name',
        'type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => 'string',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ArtworkTag $tag): void {
            if ($tag->normalized_name === null || $tag->normalized_name === '') {
                $tag->normalized_name = ArtworkTagService::normalizeName((string) $tag->name);
            }

            $tag->type = ArtworkTagType::normalize($tag->type);
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', ArtworkTagType::normalize($type));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function artworks(): BelongsToMany
    {
        return $this->belongsToMany(Artwork::class, 'artwork_tag');
    }
}
