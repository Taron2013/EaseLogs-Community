<?php

namespace App\Models;

use Database\Factories\ArtworkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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

    /**
     * @return BelongsToMany<ArtworkTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ArtworkTag::class, 'artwork_tag');
    }

    /**
     * @return list<string>
     */
    public function tagNames(): array
    {
        return $this->tags()->orderBy('name')->pluck('name')->all();
    }

    /**
     * @return list<string>
     */
    public function tagNamesForType(string $type): array
    {
        return $this->tags()
            ->where('type', \App\Support\ArtworkTagType::normalize($type))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    public function tagsGroupedByType(): array
    {
        return app(\App\Services\ArtworkTagService::class)->tagsByTypeFromArtwork($this);
    }

    /**
     * @return HasOne<ArtworkPublishingProfile, $this>
     */
    public function publishingProfile(): HasOne
    {
        return $this->hasOne(ArtworkPublishingProfile::class);
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

    /**
     * Effective start date for display when start_date is unset on older/imported records.
     */
    public function displayStartDate(): Carbon
    {
        if ($this->start_date !== null) {
            return $this->start_date->copy();
        }

        if ($this->completed_date !== null) {
            return $this->completed_date->copy();
        }

        return ($this->created_at ?? now())->copy()->startOfDay();
    }

    public function formattedDisplayStartDate(): string
    {
        return $this->displayStartDate()->format('Y-m-d');
    }

    /**
     * Default value for the start_date input on create/edit forms.
     */
    public function formStartDateValue(): string
    {
        if ($this->start_date !== null) {
            return $this->start_date->format('Y-m-d');
        }

        if (! $this->exists) {
            return now()->format('Y-m-d');
        }

        return $this->formattedDisplayStartDate();
    }

    public function formattedDisplayCompletedDate(): string
    {
        return $this->completed_date?->format('Y-m-d') ?? 'In Progress';
    }
}
