<?php

namespace App\Models;

use Database\Factories\ArtworkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Artwork extends Model
{
    /** @use HasFactory<ArtworkFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'title' => '',
        'dimension_unit' => 'in',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'start_date',
        'completed_date',
        'artwork_type',
        'medium',
        'height',
        'width',
        'depth',
        'dimension_unit',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'completed_date' => 'date',
            'height' => 'decimal:2',
            'width' => 'decimal:2',
            'depth' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ArtworkPhoto::class);
    }

    public function latestPhoto(): HasOne
    {
        return $this->hasOne(ArtworkPhoto::class)->ofMany(
            ['is_primary' => 'max', 'uploaded_at' => 'max', 'id' => 'max'],
        );
    }

    /**
     * Approved display label for dimensions (height × width × depth unit).
     */
    public function isCompleted(): bool
    {
        return $this->completed_date !== null;
    }

    /**
     * Label for lists and forms; blank/whitespace-only titles show as Untitled without persisting that string.
     */
    public function displayTitle(): string
    {
        $title = trim($this->title ?? '');

        return $title === '' ? 'Untitled' : $title;
    }

    public function formattedDimensions(): ?string
    {
        if ($this->height === null && $this->width === null && $this->depth === null) {
            return null;
        }

        $height = $this->height ?? '?';
        $width = $this->width ?? '?';
        $depth = $this->depth ?? '?';

        return "{$height} × {$width} × {$depth} {$this->dimension_unit}";
    }
}
